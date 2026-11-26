<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tv\ManageTvRundownAction;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Jobs\RunBroadcastStageJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\Visuals\StaticCardShotVisual;
use App\Services\Broadcast\Visuals\StoryboardShotVisual;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RundownProjectUnificationAndVisualResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_broadcast_production_reuses_rundown_project(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $action = app(ManageTvRundownAction::class);

        $rundown = $action->createFromPromptOrSource([
            'title' => 'Programa Unificado',
            'script_text' => 'Guion de prueba para verificar la reutilización del proyecto.',
            'source_kind' => 'manual',
        ], $owner);

        $this->assertSame(1, Project::count());
        $this->assertNotNull($rundown->project_id);

        $dispatched = $action->dispatchBroadcastProduction($rundown);

        $this->assertSame(1, Project::count());
        $this->assertNotNull($dispatched->broadcastJob);
        $this->assertSame($rundown->project_id, $dispatched->broadcastJob->project_id);

        Queue::assertPushed(RunBroadcastStageJob::class);
    }

    public function test_storyboard_shot_visual_retries_without_reference_and_falls_back_to_previous_shot(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id,
            'title' => 'Proyecto Resiliencia',
            'status' => 'draft',
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. ESTUDIO - DÍA',
            'time_of_day' => 'DÍA',
        ]);

        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::RAW->value,
            'raw_text' => 'Contenido de prueba.',
        ]);

        Storage::disk('local')->put('storyboards/shot_1.png', 'png-shot-1');
        Storage::disk('local')->put('storyboards/shot_2_retry.png', 'png-shot-2-retry');

        Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'WIDE',
            'description' => 'Plano ancla 1',
            'storyboard_image_path' => 'storyboards/shot_1.png',
        ]);

        $shot2 = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'CLOSE',
            'description' => 'Plano 2 sin imagen previa',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ai-today',
            'format' => 'pill',
            'title' => 'Broadcast Test',
            'raw_text' => 'Contenido de prueba.',
            'language' => 'es',
            'visual_mode' => 'editorial_documentary_35mm',
            'stage' => BroadcastJobStage::RAW,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
        ]);

        $expectedAnchorBase64 = base64_encode('png-shot-1');

        // Caso 1: falla con referencia, reintenta sin referencia y devuelve la imagen del segundo intento
        $storyboardServiceRetry = Mockery::mock(StoryboardService::class);
        $storyboardServiceRetry->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(function (...$args) use ($expectedAnchorBase64): bool {
                $ref = array_key_exists('styleReferenceImageBase64', $args)
                    ? $args['styleReferenceImageBase64']
                    : ($args[7] ?? null);

                return $ref === $expectedAnchorBase64;
            })
            ->andThrow(new RuntimeException('Fallo con imagen de referencia'));

        $storyboardServiceRetry->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(function (...$args): bool {
                $ref = array_key_exists('styleReferenceImageBase64', $args)
                    ? $args['styleReferenceImageBase64']
                    : (array_key_exists(7, $args) ? $args[7] : 'missing');

                return $ref === null;
            })
            ->andReturn('storyboards/shot_2_retry.png');

        $visualRetry = new StoryboardShotVisual($storyboardServiceRetry, app(StaticCardShotVisual::class));
        $retryResult = $visualRetry->generateVisual($job, $shot2);

        $this->assertSame(Storage::disk('local')->path('storyboards/shot_2_retry.png'), $retryResult);

        // Caso 2: fallan ambos intentos y reutiliza la imagen válida del plano #1 en lugar de StaticCardShotVisual
        $storyboardServiceBothFail = Mockery::mock(StoryboardService::class);
        $storyboardServiceBothFail->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(function (...$args) use ($expectedAnchorBase64): bool {
                $ref = array_key_exists('styleReferenceImageBase64', $args)
                    ? $args['styleReferenceImageBase64']
                    : ($args[7] ?? null);

                return $ref === $expectedAnchorBase64;
            })
            ->andThrow(new RuntimeException('Fallo primer intento'));

        $storyboardServiceBothFail->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(function (...$args): bool {
                $ref = array_key_exists('styleReferenceImageBase64', $args)
                    ? $args['styleReferenceImageBase64']
                    : (array_key_exists(7, $args) ? $args[7] : 'missing');

                return $ref === null;
            })
            ->andThrow(new RuntimeException('Fallo segundo intento'));

        $visualFallback = new StoryboardShotVisual($storyboardServiceBothFail, app(StaticCardShotVisual::class));
        $fallbackResult = $visualFallback->generateVisual($job, $shot2);

        $this->assertSame(Storage::disk('local')->path('storyboards/shot_1.png'), $fallbackResult);
        $this->assertNotSame(storage_path("app/private/broadcast/{$job->id}/visuals/shot_2.png"), $fallbackResult);
    }
}
