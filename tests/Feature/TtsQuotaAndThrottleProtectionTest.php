<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\User;
use App\Services\TextToSpeechService;
use App\Services\Tts\Contracts\TtsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class TtsQuotaAndThrottleProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_voice_clamps_long_text_to_100_characters(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        // Texto largo de 200 caracteres
        $longText = str_repeat('Texto largo de prueba para verificar truncado. ', 5);
        $this->assertGreaterThan(100, mb_strlen($longText));

        $mockTts = Mockery::mock(TextToSpeechService::class);
        $mockTts->shouldReceive('getDefaultVoice')->andReturn('Puck');
        $mockTts->shouldReceive('getAvailableVoices')->andReturn([
            ['id' => 'Puck', 'name' => 'Puck', 'gender' => 'male', 'lang' => 'es_ES'],
        ]);

        $mockTts->shouldReceive('synthesize')
            ->once()
            ->withArgs(function ($text, $voice, $speed, $proj) {
                // El texto debe haber sido truncado a máximo 100 caracteres
                return mb_strlen($text) <= 100 && $voice === 'Puck';
            })
            ->andReturn(storage_path('tts_cache/dummy.wav'));

        $this->app->instance(TextToSpeechService::class, $mockTts);

        // Crear dummy file para evitar excepción al responder file
        if (! file_exists(storage_path('tts_cache'))) {
            mkdir(storage_path('tts_cache'), 0775, true);
        }
        file_put_contents(storage_path('tts_cache/dummy.wav'), 'RIFF...');

        $response = $this->get(route('projects.tts.demo-voice', [
            'project' => $project->id,
            'voice' => 'Puck',
            'text' => $longText,
        ]));

        $response->assertOk();
    }

    public function test_tts_routes_have_throttle_middleware_configured(): void
    {
        $routeManifest = Route::getRoutes()->getByName('projects.tts.manifest');
        $routeStream = Route::getRoutes()->getByName('projects.tts.stream');
        $routeDemo = Route::getRoutes()->getByName('projects.tts.demo-voice');
        $routeExportMp3 = Route::getRoutes()->getByName('projects.export-mp3');

        $this->assertNotNull($routeManifest);
        $this->assertContains('throttle:60,1', $routeManifest->gatherMiddleware());

        $this->assertNotNull($routeStream);
        $this->assertContains('throttle:120,1', $routeStream->gatherMiddleware());

        $this->assertNotNull($routeDemo);
        $this->assertContains('throttle:30,1', $routeDemo->gatherMiddleware());

        $this->assertNotNull($routeExportMp3);
        $this->assertContains('throttle:15,1', $routeExportMp3->gatherMiddleware());
    }

    public function test_synthesis_skips_google_ai_provider_when_user_has_exhausted_daily_token_quota(): void
    {
        $user = User::factory()->create([
            'daily_token_limit' => 10,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Agotar cuota registrando consumo que supere el límite
        AgentRequestLog::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'tool_name' => 'test_exhaust',
            'total_tokens' => 100,
            'input_tokens' => 50,
            'output_tokens' => 50,
            'estimated_cost_usd' => 0.01,
            'input_payload' => '{}',
            'output_payload' => '{}',
        ]);

        $this->assertFalse($user->hasDailyTokensAvailable(5));

        $mockGoogleProvider = Mockery::mock(TtsProviderInterface::class);
        $mockGoogleProvider->shouldReceive('isAvailable')->andReturn(true);
        // Si el usuario agotó la cuota, Google AI NUNCA debe ser invocado
        $mockGoogleProvider->shouldNotReceive('synthesize');

        $mockSpeachesProvider = Mockery::mock(TtsProviderInterface::class);
        $mockSpeachesProvider->shouldReceive('synthesize')
            ->once()
            ->andReturn(storage_path('tts_cache/fallback_dummy.wav'));

        $tts = new TextToSpeechService;
        // Inyectar proveedores mockeados mediante reflexión
        $refGoogle = new \ReflectionProperty(TextToSpeechService::class, 'googleAiProvider');
        $refGoogle->setValue($tts, $mockGoogleProvider);

        $refSpeaches = new \ReflectionProperty(TextToSpeechService::class, 'speachesProvider');
        $refSpeaches->setValue($tts, $mockSpeachesProvider);

        // Desactivar el bypass de testing para probar el flujo real de proveedores
        Http::macro('tts_testing_real', fn () => true);

        // Asegurar que el archivo no existe previamente en caché
        $cachedPath = $tts->getCachedAudioPath('Texto nuevo para probar agotamiento de cuota', 'Puck');
        if (file_exists($cachedPath)) {
            unlink($cachedPath);
        }

        $resultPath = $tts->synthesize(
            text: 'Texto nuevo para probar agotamiento de cuota',
            voice: 'Puck',
            speed: 1.0,
            project: $project,
            user: $user
        );

        $this->assertSame(storage_path('tts_cache/fallback_dummy.wav'), $resultPath);
    }
}
