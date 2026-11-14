<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\SemanticPipelineCacheService;
use App\Services\ScriptPipelineService;
use App\Services\Security\ProjectEncryptionVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectEncryptionVaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_stores_and_encrypts_byok_credential(): void
    {
        $user = User::factory()->create();
        $vault = new ProjectEncryptionVaultService;

        $credential = $vault->storeCredential($user, 'google', 'AIzaSyA_test_secret_key_1234');

        $this->assertInstanceOf(UserApiCredential::class, $credential);
        $this->assertEquals('google', $credential->provider);
        $this->assertEquals('...1234', $credential->key_fingerprint);
        $this->assertTrue($credential->is_active);

        // Verifica que la clave en memoria se desencripta correctamente
        $this->assertEquals('AIzaSyA_test_secret_key_1234', $credential->getDecryptedKey());

        // Verifica que en la base de datos está cifrada (no en texto plano)
        $rawDbRecord = DB::table('user_api_credentials')->where('id', $credential->id)->first();
        $this->assertNotNull($rawDbRecord);
        $this->assertNotEquals('AIzaSyA_test_secret_key_1234', $rawDbRecord->encrypted_api_key);
    }

    public function test_vault_resolves_credential_for_user_and_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $vault = new ProjectEncryptionVaultService;

        $vault->storeCredential($user, 'groq', 'gsk_test_groq_key_9999');

        $this->assertTrue($vault->hasActiveCredential($user, 'groq'));
        $resolvedForUser = $vault->resolveCredentialForUser($user, 'groq');
        $this->assertNotNull($resolvedForUser);
        $this->assertEquals('gsk_test_groq_key_9999', $resolvedForUser->getDecryptedKey());

        $resolvedForProject = $vault->resolveCredentialForProject($project, 'groq');
        $this->assertNotNull($resolvedForProject);
        $this->assertEquals('...9999', $resolvedForProject->key_fingerprint);

        // Revocar credencial
        $this->assertTrue($vault->revokeCredential($user, 'groq'));
        $this->assertFalse($vault->hasActiveCredential($user, 'groq'));
        $this->assertNull($vault->resolveCredentialForUser($user, 'groq'));
    }

    public function test_semantic_pipeline_cache_computes_deterministic_sha256_hash(): void
    {
        $cacheService = new SemanticPipelineCacheService;

        $payloadA = [
            'raw_text' => 'EXT. PLAYA - DÍA. Javi camina por la orilla.',
            'characters_context' => [['name' => 'JAVI']],
            'nonce' => 'transient_nonce_123',
            'api_key' => 'secret_key_to_ignore',
        ];

        $payloadB = [
            'characters_context' => [['name' => 'JAVI']],
            'raw_text' => 'EXT. PLAYA - DÍA. Javi camina por la orilla.',
            'nonce' => 'different_nonce_456',
            'api_key' => 'another_key',
        ];

        $hashA = $cacheService->computeHash('treatment_structuring', $payloadA);
        $hashB = $cacheService->computeHash('treatment_structuring', $payloadB);

        // El hash canónico debe ser exactamente idéntico al descartar claves efímeras y ordenar campos
        $this->assertEquals($hashA, $hashB);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashA);
    }

    public function test_semantic_pipeline_cache_stores_and_retrieves_results(): void
    {
        Cache::flush();
        $cacheService = new SemanticPipelineCacheService;

        $payload = [
            'raw_text' => 'El detective llega al almacén abandonado.',
        ];

        $this->assertNull($cacheService->get('treatment_structuring', $payload));

        $result = [
            'output' => 'Tratamiento estructurado del detective en el almacén.',
            'source' => 'agent',
            'degraded' => false,
        ];

        $cacheService->put('treatment_structuring', $payload, $result);

        $cached = $cacheService->get('treatment_structuring', $payload);
        $this->assertNotNull($cached);
        $this->assertEquals('Tratamiento estructurado del detective en el almacén.', $cached['output']);
        $this->assertEquals('semantic_cache', $cached['source']);
        $this->assertTrue($cached['cached']);
    }

    public function test_byok_requests_are_exempt_from_platform_user_daily_token_limit(): void
    {
        $user = User::factory()->create([
            'daily_token_limit' => 1000,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);

        $telemetryService = new AgentTelemetryService;

        // Registrar petición estándar (no BYOK) consumiendo 800 tokens
        $telemetryService->record(
            projectId: $project->id,
            scriptBlockId: null,
            toolName: 'treatment_structuring',
            inputPayload: 'entrada',
            outputPayload: 'salida',
            telemetry: ['tokens' => ['prompt_tokens' => 400, 'completion_tokens' => 400]],
            userId: $user->id,
            isByok: false
        );

        $this->assertEquals(800, $user->getTokensUsedToday(fresh: true));

        // Registrar petición BYOK consumiendo 50.000 tokens propios
        $telemetryService->record(
            projectId: $project->id,
            scriptBlockId: null,
            toolName: 'treatment_structuring',
            inputPayload: 'entrada masiva',
            outputPayload: 'salida masiva',
            telemetry: ['tokens' => ['prompt_tokens' => 25000, 'completion_tokens' => 25000]],
            userId: $user->id,
            isByok: true
        );

        // Los tokens usados imputados a la plataforma siguen siendo 800 (los 50k BYOK están exentos)
        $this->assertEquals(800, $user->getTokensUsedToday(fresh: true));

        // Comprobar el log de la petición BYOK
        $byokLog = AgentRequestLog::where('is_byok', true)->first();
        $this->assertNotNull($byokLog);
        $this->assertEquals(50000, $byokLog->total_tokens);
        $this->assertEquals(0.0, (float) $byokLog->estimated_cost_usd);

        // Conectar clave BYOK activa y comprobar que assertUserQuotaAvailable no lanza excepción aunque el cupo esté cerca del límite
        $vault = new ProjectEncryptionVaultService;
        $vault->storeCredential($user, 'google', 'AIzaSy_fake_active_key_1234');

        $pipeline = app(ScriptPipelineService::class);
        // Debe pasar sin lanzar QuotaExceededException porque el usuario tiene credencial BYOK activa
        $pipeline->assertUserQuotaAvailable($project, 500);
        $this->assertTrue(true);
    }
}
