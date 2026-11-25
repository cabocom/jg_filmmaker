<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Studio\AuditBibleEntityUsageAction;
use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TokenPricingRate;
use App\Models\User;
use App\Services\AgentTelemetryService;
use App\Services\Pipeline\BibleSyncService;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentTelemetryAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected Scene $scene;

    protected Shot $shot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create(['daily_token_limit' => 10000]);
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->scene = Scene::factory()->create(['project_id' => $this->project->id, 'scene_order' => 1]);
        $this->shot = Shot::factory()->create(['scene_id' => $this->scene->id, 'shot_number' => 1]);
    }

    public function test_storyboard_generation_records_telemetry_in_database(): void
    {
        $fakeBase64 = base64_encode('fake_rendered_storyboard_bytes');

        Http::fake([
            '*/tools/generate_storyboard/execute' => Http::response([
                'success' => true,
                'metadata' => [
                    'image_base64' => $fakeBase64,
                    'mime_type' => 'image/png',
                    'provider' => 'google_ai_studio',
                    'model' => 'gemini-2.5-flash-image',
                    'input_tokens' => 450,
                    'output_tokens' => 60,
                ],
            ], 200),
        ]);

        $service = app(StoryboardService::class);
        $service->generateStoryboard($this->shot, 'grayscale_sketch');

        $this->assertDatabaseHas('agent_request_logs', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'tool_name' => 'generate_storyboard',
            'total_tokens' => 350,
        ]);
    }

    public function test_storyboard_generation_fails_when_user_has_insufficient_tokens(): void
    {
        $exhaustedUser = User::factory()->create(['daily_token_limit' => 100]);
        $project = Project::factory()->create(['user_id' => $exhaustedUser->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $service = app(StoryboardService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Saldo insuficiente de tokens diarios');

        $service->generateStoryboard($shot, 'grayscale_sketch');
    }

    public function test_extract_characters_records_telemetry(): void
    {
        Http::fake([
            '*/tools/extract_characters/execute' => Http::response([
                'success' => true,
                'metadata' => [
                    'provider' => 'google_ai_studio',
                    'model' => 'gemini-2.5-flash',
                    'input_tokens' => 180,
                    'output_tokens' => 45,
                    'characters' => [
                        ['name' => 'VALERIA', 'role' => 'Protagonista', 'bio' => 'Científica.', 'is_speaking' => true],
                    ],
                ],
            ], 200),
        ]);

        $syncService = app(BibleSyncService::class);
        $syncService->inferCharactersFromText('VALERIA camina por la estancia.', [], $this->project);

        $this->assertDatabaseHas('agent_request_logs', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'tool_name' => 'extract_characters',
        ]);
    }

    public function test_audit_character_usage_records_telemetry(): void
    {
        $character = Character::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'HECTOR',
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "HECTOR\nNo hay tiempo que perder.",
        ]);

        Http::fake([
            '*/tools/audit_character_usage/execute' => Http::response([
                'success' => true,
                'metadata' => [
                    'in_use' => true,
                    'is_dramatic_entity' => true,
                    'provider' => 'google_ai_studio',
                    'model' => 'gemini-2.5-flash',
                    'input_tokens' => 120,
                    'output_tokens' => 20,
                ],
            ], 200),
        ]);

        $auditAction = app(AuditBibleEntityUsageAction::class);
        $usages = $auditAction->auditCharacterUsages($this->project, $character);

        $this->assertNotEmpty($usages);
        $this->assertDatabaseHas('agent_request_logs', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'script_block_id' => $block->id,
            'tool_name' => 'audit_character_usage',
        ]);
    }

    public function test_telemetry_service_selects_rate_matching_provider_and_model(): void
    {
        // Tarifa por defecto
        TokenPricingRate::create([
            'provider' => 'default_provider',
            'model' => 'default_model',
            'input_cost_per_million' => 1.000000,
            'output_cost_per_million' => 2.000000,
            'is_active' => true,
        ]);

        // Tarifa específica para Gemini Flash
        TokenPricingRate::create([
            'provider' => 'google_ai_studio',
            'model' => 'gemini-2.5-flash',
            'input_cost_per_million' => 0.150000,
            'output_cost_per_million' => 0.600000,
            'is_active' => true,
        ]);

        $telemetryService = app(AgentTelemetryService::class);
        $log = $telemetryService->record(
            projectId: $this->project->id,
            scriptBlockId: null,
            toolName: 'treatment_structuring',
            inputPayload: 'Test input payload',
            outputPayload: 'Test output payload',
            telemetry: [
                'provider' => 'google_ai_studio',
                'model' => 'gemini-2.5-flash',
                'input_tokens' => 1_000_000,
                'output_tokens' => 1_000_000,
            ],
            userId: $this->user->id
        );

        $this->assertNotNull($log);
        // Coste esperado: (1 * 0.15) + (1 * 0.60) = 0.75 USD
        $this->assertEqualsWithDelta(0.75, (float) $log->estimated_cost_usd, 0.001);
    }

    public function test_deterministic_engine_fallback_does_not_consume_user_quota(): void
    {
        $initialTokens = $this->user->getTokensUsedToday();

        $telemetryService = app(AgentTelemetryService::class);
        $log = $telemetryService->record(
            projectId: $this->project->id,
            scriptBlockId: null,
            toolName: 'screenplay_formatter',
            inputPayload: 'Texto en bruto de prueba bastante largo para estimar tokens.',
            outputPayload: 'Texto formateado simulado.',
            telemetry: [
                'provider' => 'deterministic_engine',
                'model' => 'rule-based-nlp',
                'source' => 'heuristic_fallback',
                'degraded' => true,
            ],
            userId: $this->user->id
        );

        $this->assertNotNull($log);
        $this->assertSame(0, $log->total_tokens);
        $this->assertSame(0.0, (float) $log->estimated_cost_usd);
        $this->assertIsArray($log->meta);
        $this->assertTrue($log->meta['rate_snapshot']['is_deterministic']);

        // La cuota del usuario no se decrementa
        $this->assertSame($initialTokens, $this->user->getTokensUsedToday(true));
    }

    public function test_telemetry_records_exact_pricing_rate_snapshot_in_meta(): void
    {
        TokenPricingRate::create([
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
            'input_cost_per_million' => 3.0,
            'output_cost_per_million' => 15.0,
            'is_active' => true,
        ]);

        $telemetryService = app(AgentTelemetryService::class);
        $log = $telemetryService->record(
            projectId: $this->project->id,
            scriptBlockId: null,
            toolName: 'screenplay_formatter',
            inputPayload: 'Test input payload',
            outputPayload: 'Test output payload',
            telemetry: [
                'provider' => 'anthropic',
                'model' => 'claude-3-5-sonnet',
                'usage' => [
                    'prompt_tokens' => 1000,
                    'completion_tokens' => 500,
                ],
            ],
            userId: $this->user->id
        );

        $this->assertNotNull($log);
        $this->assertIsArray($log->meta);
        $this->assertArrayHasKey('rate_snapshot', $log->meta);
        $this->assertSame(3.0, (float) $log->meta['rate_snapshot']['input_cost_per_million']);
        $this->assertSame(15.0, (float) $log->meta['rate_snapshot']['output_cost_per_million']);
        $this->assertSame('anthropic', $log->meta['rate_snapshot']['provider']);
        $this->assertSame('claude-3-5-sonnet', $log->meta['rate_snapshot']['model']);
    }
}
