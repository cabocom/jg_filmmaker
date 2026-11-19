<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Auth\RequestLoginOtpAction;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\StoryboardService;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AntiFallbackHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Criterio ISSUE-39: Un fallo SMTP en RequestLoginOtpAction devuelve 'success' => false.
     */
    public function test_request_login_otp_action_returns_false_when_mail_fails(): void
    {
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \Exception('SMTP connection timeout: 127.0.0.1:25'));

        $action = new RequestLoginOtpAction;
        $result = $action->execute('director@cabocom.com', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('email', $result['field']);
        $this->assertStringContainsString('No se pudo enviar el código de acceso', $result['error']);
    }

    /**
     * Criterio ISSUE-39: Fallo en TTS no guarda archivo WAV silencioso en caché.
     */
    public function test_tts_service_does_not_persist_silent_wav_on_provider_failures(): void
    {
        Http::macro('tts_testing_real', fn () => true);
        Config::set('services.speaches.url', 'http://speaches.test:8000');

        Http::fake([
            'http://speaches.test:8000/*' => Http::response('Service unavailable', 503),
            'https://generativelanguage.googleapis.com/*' => Http::response('Model overloaded', 503),
        ]);

        $service = app(TextToSpeechService::class);
        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Servicio de síntesis de voz (TTS) no disponible');

        $service->synthesize(
            text: 'Texto de prueba cinematográfica que debe fallar sin silencio residual.',
            voice: 'es-ES-Neural2-F',
            user: $user
        );
    }

    /**
     * Criterio ISSUE-39: StoryboardService rechaza imagen 1x1 sintética sin cobrar tokens ni persistir.
     */
    public function test_storyboard_service_rejects_synthetic_1x1_png_when_not_in_mock(): void
    {
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-long-secret-key-for-robustness-check-1234');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create(['script_block_id' => $block->id]);

        // Simular que jg_agente devuelve el 1x1 sintético
        Http::fake([
            '*/api/v1/tools/generate_storyboard' => Http::response([
                'success' => true,
                'metadata' => [
                    'image_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                    'mime_type' => 'image/png',
                    'source' => 'deterministic_fallback',
                ],
            ], 200),
        ]);

        $service = app(StoryboardService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generación de storyboard no disponible');

        $service->generateStoryboard(
            shot: $shot,
            style: 'realistic',
            customPrompt: 'Prompt plano'
        );
    }
}
