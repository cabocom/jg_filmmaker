<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\RenderShotClipAction;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Jobs\RenderBroadcastShotJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\BroadcastMediaValidator;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\Broadcast\Visuals\GenerativeVideoShotVisual;
use App\Services\Broadcast\Visuals\ShotVisualStrategyResolver;
use App\Services\Broadcast\Visuals\StaticCardShotVisual;
use App\Services\Broadcast\Visuals\StoryboardShotVisual;
use App\Services\JgAgente\HmacSigner;
use App\Services\Media\MediaStorageService;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BroadcastRenderPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $testSubDir = storage_path('framework/testing');
        if (File::isDirectory($testSubDir)) {
            File::delete(File::glob("{$testSubDir}/test_sub*") ?: []);
        }

        $broadcastDir = storage_path('app/private/broadcast');
        if (File::isDirectory($broadcastDir)) {
            File::deleteDirectory($broadcastDir);
        }

        parent::tearDown();
    }

    /**
     * Test 1: ShotVisualStrategyResolver resuelve static_card, storyboard y generative_video;
     * StaticCardShotVisual genera PNG no vacío y GenerativeVideoShotVisual valida tipo y requiere clip_path.
     */
    public function test_visual_strategy_resolver_selects_static_card_storyboard_and_generative_video(): void
    {
        $resolver = app(ShotVisualStrategyResolver::class);

        // 1. Static Card Strategy
        $staticStrategy = $resolver->resolve('static_card');
        $this->assertInstanceOf(StaticCardShotVisual::class, $staticStrategy);
        $this->assertSame('image', $staticStrategy->visualType());

        // 2. Storyboard Strategy
        $storyboardStrategy = $resolver->resolve('storyboard');
        $this->assertInstanceOf(StoryboardShotVisual::class, $storyboardStrategy);
        $this->assertSame('image', $storyboardStrategy->visualType());

        // 3. Generative Video Strategy
        $generativeStrategy = $resolver->resolve('generative_video');
        $this->assertInstanceOf(GenerativeVideoShotVisual::class, $generativeStrategy);
        $this->assertSame('video', $generativeStrategy->visualType());

        // Preparar modelos mínimos para prueba de generación visual
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Visual Test Project',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DIA',
            'time_of_day' => 'DIA',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Visual generation test',
        ]);
        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'Plano general con tarjeta estática',
        ]);
        $job = BroadcastJob::create([
            'channel_id' => 'canal_test',
            'format' => 'pill',
            'title' => 'Titular Informativo de Prueba',
            'raw_text' => 'Texto bruto de prueba',
            'language' => 'es',
            'sources' => ['EFE'],
            'visual_mode' => 'static_card',
            'callback_url' => 'https://example.com/callback',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [],
        ]);

        // Verificar que StaticCardShotVisual genera un PNG no vacío en disco
        $pngPath = $staticStrategy->generateVisual($job, $shot, [
            'speaker' => 'Presentador Test',
            'progress' => 0.5,
        ]);
        $this->assertFileExists($pngPath);
        $this->assertGreaterThan(0, filesize($pngPath));

        // Verificar que GenerativeVideoShotVisual lanza RuntimeException si no cuenta con clip_path válido
        $thrown = false;
        try {
            $generativeStrategy->generateVisual($job, $shot, []);
        } catch (RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('GenerativeVideoShotVisual', $e->getMessage());
        }
        $this->assertTrue($thrown, 'GenerativeVideoShotVisual debía lanzar RuntimeException sin clip_path.');
    }

    /**
     * Test 2: ShotSubtitleGenerator crea archivos .srt y .ass verticales con formato y cabeceras válidas.
     */
    public function test_subtitle_generator_creates_valid_srt_and_vertical_ass_files(): void
    {
        $generator = new ShotSubtitleGenerator;
        $testDir = storage_path('framework/testing');
        File::ensureDirectoryExists($testDir);

        $srtPath = "{$testDir}/test_sub_valid.srt";
        $assPath = "{$testDir}/test_sub_vertical.ass";

        $oralText = 'Bienvenidos a la emisión de prueba para la verificación de subtítulos sincronizados.';
        $durationSeconds = 15.0;

        // Generar SRT
        $generator->generateSrt($oralText, $durationSeconds, $srtPath);
        $this->assertFileExists($srtPath);
        $srtContent = (string) file_get_contents($srtPath);
        $this->assertStringContainsString('-->', $srtContent);
        $this->assertStringContainsString('00:00:00,000 -->', $srtContent);

        // Generar ASS vertical (Short 9:16)
        $generator->generateAss($oralText, $durationSeconds, $assPath, verticalShort: true);
        $this->assertFileExists($assPath);
        $assContent = (string) file_get_contents($assPath);
        $this->assertStringContainsString('[Script Info]', $assContent);
        $this->assertStringContainsString('PlayResX: 1080', $assContent);
        $this->assertStringContainsString('PlayResY: 1920', $assContent);
        $this->assertStringContainsString('Dialogue:', $assContent);
    }

    /**
     * Test 3: BroadcastMediaValidator acepta 30fps CFR con audio AAC y rechaza streams con fps dispares o sin audio.
     */
    public function test_ffprobe_validator_accepts_valid_30fps_cfr_aac_and_rejects_invalid_streams(): void
    {
        $validator = app(BroadcastMediaValidator::class);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_probe_');
        $this->assertIsString($tempFile);
        file_put_contents($tempFile, 'DUMMY_MP4_CONTENT_FOR_FFPROBE');

        try {
            // 1. Caso válido: H.264, 30fps CFR (r_frame_rate == avg_frame_rate == 30/1), audio AAC
            $validProbeOutput = [
                'streams' => [
                    [
                        'codec_type' => 'video',
                        'codec_name' => 'h264',
                        'r_frame_rate' => '30/1',
                        'avg_frame_rate' => '30/1',
                        'duration' => '18.5',
                    ],
                    [
                        'codec_type' => 'audio',
                        'codec_name' => 'aac',
                        'sample_rate' => '48000',
                        'bit_rate' => '128000',
                        'duration' => '18.5',
                    ],
                ],
                'format' => [
                    'duration' => '18.5',
                    'bit_rate' => '128000',
                ],
            ];

            Process::fake([
                '*ffprobe*' => Process::result(output: (string) json_encode($validProbeOutput)),
            ]);

            $validReport = $validator->validateMp4($tempFile, forceProbe: true);
            $this->assertTrue($validReport['valid']);
            $this->assertSame(30.0, $validReport['fps']);
            $this->assertTrue($validReport['is_cfr']);
            $this->assertSame('h264', $validReport['video_codec']);
            $this->assertSame('aac', $validReport['audio_codec']);
            $this->assertEmpty($validReport['errors']);

            $assertValidReport = $validator->assertValidMp4($tempFile, forceProbe: true);
            $this->assertTrue($assertValidReport['valid']);

            // 2. Caso inválido: avg_frame_rate no CFR (24/1 != 30/1)
            $invalidFpsOutput = $validProbeOutput;
            $invalidFpsOutput['streams'][0]['avg_frame_rate'] = '24/1';

            Process::fake([
                '*ffprobe*' => Process::result(output: (string) json_encode($invalidFpsOutput)),
            ]);

            $invalidFpsReport = $validator->validateMp4($tempFile, forceProbe: true);
            $this->assertFalse($invalidFpsReport['valid']);
            $this->assertNotEmpty($invalidFpsReport['errors']);

            $fpsExceptionThrown = false;
            try {
                $validator->assertValidMp4($tempFile, forceProbe: true);
            } catch (RuntimeException $e) {
                $fpsExceptionThrown = true;
                $this->assertStringContainsString('MP4 media validation failed', $e->getMessage());
            }
            $this->assertTrue($fpsExceptionThrown);

            // 3. Caso inválido: sin stream de audio AAC
            $noAudioOutput = [
                'streams' => [
                    $validProbeOutput['streams'][0],
                ],
                'format' => $validProbeOutput['format'],
            ];

            Process::fake([
                '*ffprobe*' => Process::result(output: (string) json_encode($noAudioOutput)),
            ]);

            $noAudioReport = $validator->validateMp4($tempFile, forceProbe: true);
            $this->assertFalse($noAudioReport['valid']);
            $this->assertContains('No audio stream found in MP4 file.', $noAudioReport['errors']);

            $audioExceptionThrown = false;
            try {
                $validator->assertValidMp4($tempFile, forceProbe: true);
            } catch (RuntimeException $e) {
                $audioExceptionThrown = true;
                $this->assertStringContainsString('MP4 media validation failed', $e->getMessage());
            }
            $this->assertTrue($audioExceptionThrown);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Test 4: Flujo end-to-end de renderizado desde SHOTS genera mp4 1080p, mp3 podcast, short 9:16 y purga temporales.
     */
    public function test_end_to_end_render_from_shots_generates_mp4_mp3_and_short_and_purges_temp_clips(): void
    {
        $secret = 'test-broadcast-secret-1234567890';
        config()->set('services.jg_agente.secret', $secret);

        Storage::fake('media');
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Broadcast End-to-End Test',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO BROADCAST - DIA',
            'time_of_day' => 'DIA',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Pauta broadcast completa para 3 planos',
        ]);

        $shot1 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'Apertura del programa informativo.',
        ]);
        $shot2 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'MEDIUM',
            'description' => 'Desarrollo de los puntos principales.',
        ]);
        $shot3 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 3,
            'shot_type' => 'CLOSE_UP',
            'description' => 'Conclusión y despedida del informativo.',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'canal_noticias_24h',
            'format' => 'pill',
            'title' => 'Boletín Especial de Cierre',
            'raw_text' => 'Texto completo para la síntesis de 3 planos.',
            'language' => 'es',
            'sources' => ['EFE', 'Reuters'],
            'visual_mode' => 'static_card',
            'callback_url' => 'https://example.com/callback',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'shots' => [
                    [
                        'shot_id' => (string) $shot1->id,
                        'shot_number' => 1,
                        'oral_text' => 'Comenzamos con la noticia más relevante de la jornada actual.',
                        'estimated_seconds' => 15.0,
                    ],
                    [
                        'shot_id' => (string) $shot2->id,
                        'shot_number' => 2,
                        'oral_text' => 'Analizamos los acuerdos clave y sus repercusiones directas.',
                        'estimated_seconds' => 15.0,
                    ],
                    [
                        'shot_id' => (string) $shot3->id,
                        'shot_number' => 3,
                        'oral_text' => 'Hasta aquí el resumen diario, muchas gracias por acompañarnos.',
                        'estimated_seconds' => 15.0,
                    ],
                ],
                'shots_count' => 3,
            ],
        ]);

        $path = "/api/v1/broadcast/jobs/{$job->id}/render";
        $payload = [];
        $rawBody = (string) json_encode($payload);

        $signer = new HmacSigner;
        $headers = $signer->generateSignatureHeaders(
            method: 'POST',
            path: $path,
            rawBody: $rawBody,
            secret: $secret
        );

        $response = $this->postJson($path, $payload, $headers);
        $response->assertStatus(202);
        $response->assertJson([
            'job_id' => $job->id,
        ]);

        $job->refresh();
        $this->assertSame(BroadcastJobStage::COMPLETED, $job->stage);

        $mediaStorage = app(MediaStorageService::class);
        $channelId = (string) $job->channel_id;
        $jobId = (string) $job->id;

        // Comprobar existencia en MediaStorageService
        $this->assertTrue($mediaStorage->exists("broadcast/{$channelId}/{$jobId}/episode_1080p.mp4"));
        $this->assertTrue($mediaStorage->exists("broadcast/{$channelId}/{$jobId}/episode_podcast.mp3"));
        $this->assertTrue($mediaStorage->exists("broadcast/{$channelId}/{$jobId}/episode_short_9x16.mp4"));

        // Comprobar metadatos ffprobe y purga en artifacts['exports']
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $this->assertArrayHasKey('exports', $artifacts);
        $exports = $artifacts['exports'];
        $this->assertTrue($exports['temp_clips_purged']);
        $this->assertArrayHasKey('ffprobe', $exports);
        $this->assertTrue($exports['ffprobe']['episode_1080p']['valid']);
        $this->assertTrue($exports['ffprobe']['episode_short_9x16']['valid']);

        // Comprobar que los clips temporales de shots fueron purgados
        $clipsDir = storage_path("app/private/broadcast/{$jobId}/clips");
        $this->assertDirectoryDoesNotExist($clipsDir);
        $tempDir = storage_path("app/private/broadcast/{$jobId}/temp");
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    /**
     * Test 5: Fallo en el Shot N permite reintentar aisladamente dicho shot sin regenerar shots 1..N-1.
     */
    public function test_failing_on_shot_n_allows_retrying_only_shot_n_without_regenerating_previous_shots(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Retry Test Project',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DIA',
            'time_of_day' => 'DIA',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Texto para reintento aislado',
        ]);

        $shot1 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'Plano 1 para render inicial',
        ]);
        $shot2 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'MEDIUM',
            'description' => 'Plano 2 que simulará fallo',
        ]);
        $shot3 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 3,
            'shot_type' => 'CLOSE_UP',
            'description' => 'Plano 3 pendiente',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'canal_retry',
            'format' => 'pill',
            'title' => 'Emisión para Prueba de Reintento',
            'raw_text' => 'Texto bruto de reintento',
            'language' => 'es',
            'sources' => ['EFE'],
            'visual_mode' => 'static_card',
            'callback_url' => 'https://example.com/callback',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'shots' => [
                    [
                        'shot_id' => (string) $shot1->id,
                        'shot_number' => 1,
                        'oral_text' => 'Texto oral locución plano 1.',
                        'estimated_seconds' => 15.0,
                    ],
                    [
                        'shot_id' => (string) $shot2->id,
                        'shot_number' => 2,
                        'oral_text' => 'Texto oral locución plano 2.',
                        'estimated_seconds' => 15.0,
                    ],
                    [
                        'shot_id' => (string) $shot3->id,
                        'shot_number' => 3,
                        'oral_text' => 'Texto oral locución plano 3.',
                        'estimated_seconds' => 15.0,
                    ],
                ],
                'shots_count' => 3,
            ],
        ]);

        // 1. Renderizar shot 1 exitosamente con RenderShotClipAction
        $renderAction = app(RenderShotClipAction::class);
        $shot1ClipData = $renderAction->execute($job, $shot1, [
            'oral_text' => 'Texto oral locución plano 1.',
            'estimated_seconds' => 15.0,
        ]);

        $job->refresh();
        $this->assertNotEmpty($shot1ClipData['clip_path']);
        $this->assertFileExists((string) $shot1ClipData['clip_path']);
        $this->assertArrayHasKey('rendered_shots', $job->artifacts);
        $this->assertArrayHasKey($shot1->id, $job->artifacts['rendered_shots']);

        // 2. Simular fallo en shot 2 invocando failed() en RenderBroadcastShotJob
        $failedJob = new RenderBroadcastShotJob((string) $job->id, (string) $shot2->id);
        $failedJob->failed(new RuntimeException('FFmpeg timeout shot 2'));

        $job->refresh();
        $this->assertSame(BroadcastJobStage::FAILED, $job->stage);
        $this->assertSame("RENDER_SHOT:{$shot2->id}", $job->failed_stage);
        $this->assertStringContainsString('FFmpeg timeout shot 2', (string) $job->error_message);

        // Verificar que rendered_shots conserva intacto shot 1 y no tiene shot 2
        $artifactsAfterFail = $job->artifacts;
        $this->assertArrayHasKey('rendered_shots', $artifactsAfterFail);
        $this->assertArrayHasKey($shot1->id, $artifactsAfterFail['rendered_shots']);
        $this->assertArrayNotHasKey($shot2->id, $artifactsAfterFail['rendered_shots']);

        // 3. Con Queue::fake(), reanudar renderizado con AutoGenerateBroadcastShotsAction::startRendering
        Queue::fake();

        $action = app(AutoGenerateBroadcastShotsAction::class);
        $action->startRendering($job);

        // Verificar que se encola exactamente para shot2 y NO para shot1
        Queue::assertPushed(RenderBroadcastShotJob::class, function (RenderBroadcastShotJob $queuedJob) use ($job, $shot2): bool {
            return $queuedJob->broadcastJobId === (string) $job->id && $queuedJob->shotId === (string) $shot2->id;
        });

        Queue::assertNotPushed(RenderBroadcastShotJob::class, function (RenderBroadcastShotJob $queuedJob) use ($shot1): bool {
            return $queuedJob->shotId === (string) $shot1->id;
        });

        // 4. Verificar que llamar a RenderShotClipAction::execute($job, $shot1) retorna el clip existente
        // sin llamar de nuevo a TextToSpeechService::synthesize
        $ttsSpy = $this->spy(TextToSpeechService::class);
        $reRenderAction = app(RenderShotClipAction::class);

        $cachedResult = $reRenderAction->execute($job, $shot1);
        $this->assertSame($shot1ClipData['clip_path'], $cachedResult['clip_path']);
        $ttsSpy->shouldNotHaveReceived('synthesize');
    }
}
