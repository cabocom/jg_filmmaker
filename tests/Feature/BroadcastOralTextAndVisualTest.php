<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\RenderShotClipAction;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\Visuals\StaticCardShotVisual;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class BroadcastOralTextAndVisualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();
        Storage::fake('local');
        Storage::fake('media');
    }

    protected function tearDown(): void
    {
        $broadcastDir = storage_path('app/private/broadcast');
        if (File::isDirectory($broadcastDir)) {
            File::deleteDirectory($broadcastDir);
        }

        parent::tearDown();
    }

    /**
     * Test 1: RenderShotClipAction prioritiza context oral_text o job raw_text sobre Shot->description.
     */
    public function test_render_shot_clip_action_uses_context_oral_text_or_raw_text_instead_of_shot_description(): void
    {
        Process::fake();

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Fray Leopoldo Documental',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. CHURCH - DAY',
            'time_of_day' => 'DAY',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Fray Leopoldo fue un fraile capuchino.',
        ]);
        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'INT. CHURCH - DAY. Wide shot.',
        ]);

        $rawText = 'Fray Leopoldo fue un fraile capuchino.';
        $job = BroadcastJob::create([
            'channel_id' => 'ch-fray-leopoldo',
            'format' => 'news',
            'title' => 'Historia de Fray Leopoldo',
            'raw_text' => $rawText,
            'language' => 'es',
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [],
        ]);

        $ttsMock = $this->mock(TextToSpeechService::class);
        $ttsMock->shouldReceive('synthesize')
            ->once()
            ->withArgs(function (string $text) use ($rawText): bool {
                return str_contains($text, $rawText)
                    && ! str_contains($text, 'INT. CHURCH - DAY');
            })
            ->andReturn(storage_path("app/private/broadcast/{$job->id}/audio_dummy.wav"));

        $action = app(RenderShotClipAction::class);

        // 1. Ejecutar sin context oral_text -> debe usar job raw_text
        $result = $action->execute($job, $shot, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('oral_text', $result);
        $this->assertSame($rawText, $result['oral_text']);
        $this->assertStringNotContainsString('INT. CHURCH - DAY. Wide shot.', $result['oral_text']);

        // 2. Ejecutar con context oral_text explícito
        $explicitOralText = 'Texto oral específico provisto en el contexto.';
        $shot2 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'CLOSE_UP',
            'description' => 'INT. CHURCH - DAY. Close up shot.',
        ]);

        $ttsMock->shouldReceive('synthesize')
            ->once()
            ->withArgs(function (string $text) use ($explicitOralText): bool {
                return $text === $explicitOralText
                    && ! str_contains($text, 'INT. CHURCH - DAY');
            })
            ->andReturn(storage_path("app/private/broadcast/{$job->id}/audio_dummy_2.wav"));

        $resultContext = $action->execute($job, $shot2, ['oral_text' => $explicitOralText]);

        $this->assertSame($explicitOralText, $resultContext['oral_text']);
        $this->assertStringNotContainsString('INT. CHURCH - DAY. Close up shot.', $resultContext['oral_text']);

        // 3. Ejecutar con raw_text con solo espacios -> debe caer limpiamente al job title
        $jobOnlySpaces = BroadcastJob::create([
            'channel_id' => 'ch-spaces',
            'format' => 'news',
            'title' => 'Titulo de Respaldo Limpio',
            'raw_text' => '    ',
            'language' => 'es',
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [],
        ]);

        $ttsMock->shouldReceive('synthesize')
            ->once()
            ->withArgs(function (string $text): bool {
                return $text === 'Titulo de Respaldo Limpio';
            })
            ->andReturn(storage_path("app/private/broadcast/{$jobOnlySpaces->id}/audio_dummy_3.wav"));

        $resultSpaces = $action->execute($jobOnlySpaces, $shot, []);
        $this->assertSame('Titulo de Respaldo Limpio', $resultSpaces['oral_text']);
    }

    /**
     * Test 2: AutoGenerateBroadcastShotsAction distribuye raw_text a través de los planos como oral_text.
     */
    public function test_process_shots_stage_distributes_raw_text_across_shots_as_oral_text(): void
    {
        config(['services.jg_agente.mock' => true]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Oral Text Distribution Project',
            'status' => 'in_progress',
            'storyboard_style' => 'static_card',
        ]);
        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO BROADCAST - DÍA',
            'time_of_day' => 'DÍA',
        ]);

        $rawText = 'Primera frase en español. Segunda frase en español.';
        $screenplayContent = "INT. CHURCH - DAY\n\n[WIDE SHOT] Camera pans across the ancient cathedral.\n\n[CLOSE UP] Dramatic lighting on the altar.";

        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SCREENPLAY->value,
            'raw_text' => 'Technical screenplay raw notes',
            'screenplay_content' => $screenplayContent,
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-oral-distribution',
            'format' => 'news',
            'title' => 'Emisión Oral Distribution',
            'raw_text' => $rawText,
            'language' => 'es',
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::SCREENPLAY,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [],
        ]);

        $action = app(AutoGenerateBroadcastShotsAction::class);
        $job = $action->executeStage($job, BroadcastJobStage::SHOTS);

        $freshJob = $job->fresh();
        $this->assertSame(BroadcastJobStage::SHOTS, $freshJob->stage);
        $this->assertIsArray($freshJob->artifacts);
        $this->assertArrayHasKey('shots', $freshJob->artifacts);

        $shots = $freshJob->artifacts['shots'];
        $this->assertNotEmpty($shots);

        foreach ($shots as $shot) {
            $this->assertArrayHasKey('oral_text', $shot);
            $oralText = $shot['oral_text'];
            $this->assertNotEmpty($oralText);
            $this->assertStringNotContainsString('INT.', $oralText);
            $this->assertStringNotContainsString('CHURCH', $oralText);
            $this->assertStringNotContainsString('Camera', $oralText);
            $this->assertStringNotContainsString('WIDE SHOT', $oralText);
            $this->assertTrue(
                str_contains($rawText, $oralText)
                || str_contains($oralText, 'Primera frase en español')
                || str_contains($oralText, 'Segunda frase en español')
            );
            $this->assertGreaterThan(0, $shot['word_count']);
            $this->assertGreaterThanOrEqual(12, $shot['estimated_seconds']);
        }
    }

    /**
     * Test 3: StaticCardShotVisual genera fallback determinista no verde cuando GD no está o ffmpeg se ejecuta.
     */
    public function test_static_card_shot_visual_generates_non_green_fallback_when_gd_unavailable_or_ffmpeg_runs(): void
    {
        $oldGreen1x1Base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $oldGreenBytes = base64_decode($oldGreen1x1Base64);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'title' => 'Static Card Visual Project',
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
            'raw_text' => 'Prueba visual',
        ]);
        $shot = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'MEDIUM',
            'description' => 'Plano de tarjeta estática',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-static-card',
            'format' => 'pill',
            'title' => 'Titular Static Card',
            'raw_text' => 'Texto para la tarjeta estática',
            'language' => 'es',
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'resolution_profile' => '1080p30',
            ],
        ]);

        $strategy = new StaticCardShotVisual;

        // 1. Test generateVisual() estándar
        $outputPath = $strategy->generateVisual($job, $shot);
        $this->assertFileExists($outputPath);
        $generatedBytes = (string) file_get_contents($outputPath);
        $this->assertNotEmpty($generatedBytes);
        $this->assertNotEquals($oldGreenBytes, $generatedBytes);

        // 2. Test invocación de FFmpeg para perfil 1080p30 (1920x1080)
        Process::fake([
            '*ffmpeg*' => Process::result(output: 'ffmpeg 1080p visual', exitCode: 0),
        ]);

        $reflection = new ReflectionClass($strategy);
        $ffmpegMethod = $reflection->getMethod('renderFfmpegImage');
        $ffmpegOutputPath1080 = storage_path("app/private/broadcast/{$job->id}/visuals/ffmpeg_shot_1080.png");
        File::ensureDirectoryExists(dirname($ffmpegOutputPath1080));
        File::put($ffmpegOutputPath1080, 'MOCK_FFMPEG_1080P_CONTENT');

        $ffmpegSuccess1080 = $ffmpegMethod->invoke($strategy, $job, $ffmpegOutputPath1080);
        $this->assertTrue($ffmpegSuccess1080);

        Process::assertRan(function ($process): bool {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_contains($command, 'ffmpeg')
                && str_contains($command, 'color=c=0x0f172a:s=1920x1080:d=1');
        });

        // 3. Test invocación de FFmpeg para perfil 720p30 (1280x720)
        $job720 = BroadcastJob::create([
            'channel_id' => 'ch-static-card-720',
            'format' => 'pill',
            'title' => 'Titular 720p',
            'raw_text' => 'Texto para tarjeta 720p',
            'language' => 'es',
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'resolution_profile' => '720p30',
            ],
        ]);

        $ffmpegOutputPath720 = storage_path("app/private/broadcast/{$job720->id}/visuals/ffmpeg_shot_720.png");
        File::ensureDirectoryExists(dirname($ffmpegOutputPath720));
        File::put($ffmpegOutputPath720, 'MOCK_FFMPEG_720P_CONTENT');

        $ffmpegSuccess720 = $ffmpegMethod->invoke($strategy, $job720, $ffmpegOutputPath720);
        $this->assertTrue($ffmpegSuccess720);

        Process::assertRan(function ($process): bool {
            $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

            return str_contains($command, 'ffmpeg')
                && str_contains($command, 'color=c=0x0f172a:s=1280x720:d=1');
        });

        // 4. Test FFmpeg fallo: exitCode !== 0 retorna false
        Process::fake([
            '*ffmpeg*' => Process::result(output: 'ffmpeg failed', exitCode: 1),
        ]);
        $ffmpegFailedPath = storage_path("app/private/broadcast/{$job->id}/visuals/ffmpeg_failed.png");
        File::put($ffmpegFailedPath, 'SOME_CONTENT');
        $ffmpegFailureExit = $ffmpegMethod->invoke($strategy, $job, $ffmpegFailedPath);
        $this->assertFalse($ffmpegFailureExit);

        // 5. Test FFmpeg fallo: fichero no generado o de 0 bytes retorna false
        Process::fake([
            '*ffmpeg*' => Process::result(output: 'ffmpeg exited zero but no file', exitCode: 0),
        ]);
        $ffmpegMissingPath = storage_path("app/private/broadcast/{$job->id}/visuals/ffmpeg_missing.png");
        if (File::exists($ffmpegMissingPath)) {
            File::delete($ffmpegMissingPath);
        }
        $ffmpegFailureMissing = $ffmpegMethod->invoke($strategy, $job, $ffmpegMissingPath);
        $this->assertFalse($ffmpegFailureMissing);

        // 6. Test renderFallbackPng() genera bytes válidos que NUNCA equivalen al PNG verde 1x1
        $fallbackMethod = $reflection->getMethod('renderFallbackPng');
        $fallbackOutputPath = storage_path("app/private/broadcast/{$job->id}/visuals/fallback_shot.png");

        $fallbackMethod->invoke($strategy, $fallbackOutputPath);
        $this->assertFileExists($fallbackOutputPath);
        $fallbackBytes = (string) file_get_contents($fallbackOutputPath);

        $this->assertNotEmpty($fallbackBytes);
        $this->assertNotEquals($oldGreenBytes, $fallbackBytes);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $fallbackBytes);
    }
}
