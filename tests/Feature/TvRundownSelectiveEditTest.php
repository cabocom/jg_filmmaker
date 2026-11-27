<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tv\UpdateRundownShotsAction;
use App\Enums\BroadcastJobStage;
use App\Jobs\RenderBroadcastShotJob;
use App\Livewire\Tv\TvRundownShotsModal;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\User;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TvRundownSelectiveEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerate_single_shot_visual_preserves_master_audio_and_other_rendered_shots(): void
    {
        ['rundown' => $rundown, 'job' => $job, 'shot1' => $shot1, 'shot2' => $shot2] = $this->createRundownWithTwoShots();

        $job->update(['visual_mode' => 'storyboard']);

        $this->mock(StoryboardService::class)
            ->shouldReceive('generateStoryboard')
            ->once()
            ->withArgs(static fn (Shot $shot, string $style): bool => (string) $shot->id === (string) $shot1->id
                && $style === 'editorial_documentary_35mm')
            ->andReturnUsing(static function (Shot $shot): string {
                $newPath = "projects/{$shot->scene->project_id}/storyboards/shot_{$shot->id}_v2.png";
                $shot->update(['storyboard_image_path' => $newPath]);

                return $newPath;
            });

        /** @var UpdateRundownShotsAction $action */
        $action = app(UpdateRundownShotsAction::class);
        $updatedShot = $action->regenerateSingleShotVisual($rundown, (string) $shot1->id, 'Nueva escena de servidor');

        $this->assertSame('Nueva escena de servidor', $updatedShot->description);
        $this->assertStringContainsString('_v2.png', (string) $updatedShot->storyboard_image_path);

        $job->refresh();
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];

        $this->assertSame('broadcast/master.mp3', $artifacts['master_audio']['audio_path'] ?? null);
        $this->assertCount(2, $artifacts['master_audio']['timed_shots'] ?? []);
        $this->assertStringContainsString('_v2.png', (string) ($artifacts['shot_visuals'][(string) $shot1->id] ?? ''));
        $this->assertArrayNotHasKey((string) $shot1->id, $artifacts['rendered_shots'] ?? []);
        $this->assertSame('projects/test/shot2.png', $artifacts['shot_visuals'][(string) $shot2->id] ?? null);
        $this->assertSame('broadcast/clips/shot2.mp4', $artifacts['rendered_shots'][(string) $shot2->id]['clip_path'] ?? null);
    }

    public function test_update_oral_texts_invalidates_master_audio_and_rendered_shots_but_preserves_shot_visuals(): void
    {
        ['rundown' => $rundown, 'job' => $job, 'shot1' => $shot1, 'shot2' => $shot2] = $this->createRundownWithTwoShots();

        /** @var UpdateRundownShotsAction $action */
        $action = app(UpdateRundownShotsAction::class);
        $updatedJob = $action->updateOralTextsAndInvalidateAudio($rundown, [
            (string) $shot1->id => 'Texto corregido uno.',
        ]);

        $artifacts = is_array($updatedJob->artifacts) ? $updatedJob->artifacts : [];

        $this->assertArrayNotHasKey('master_audio', $artifacts);
        $this->assertArrayNotHasKey('rendered_shots', $artifacts);
        $this->assertSame([
            (string) $shot1->id => 'projects/test/shot1.png',
            (string) $shot2->id => 'projects/test/shot2.png',
        ], $artifacts['shot_visuals'] ?? null);

        $this->assertStringContainsString('Texto corregido uno.', (string) ($artifacts['full_oral_text'] ?? ''));
        $this->assertStringContainsString('Texto corregido uno.', (string) $rundown->refresh()->script_text);
        $this->assertSame($job->id, $updatedJob->id);
    }

    public function test_reassemble_after_edits_dispatches_start_rendering_and_updates_rundown_stage(): void
    {
        Queue::fake();

        ['rundown' => $rundown, 'job' => $job, 'shot1' => $shot1, 'shot2' => $shot2] = $this->createRundownWithTwoShots();

        $existingClipPath = sys_get_temp_dir().'/shot2_test_'.uniqid().'.mp4';
        File::put($existingClipPath, 'MOCK_MP4');

        try {
            $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
            unset($artifacts['rendered_shots'][(string) $shot1->id]);
            $artifacts['rendered_shots'][(string) $shot2->id]['clip_path'] = $existingClipPath;
            $job->update(['artifacts' => $artifacts]);

            /** @var UpdateRundownShotsAction $action */
            $action = app(UpdateRundownShotsAction::class);
            $updatedRundown = $action->reassembleAfterEdits($rundown);

            $this->assertSame('rendering', $updatedRundown->stage);
            $this->assertSame('rendering', $updatedRundown->status);
            $this->assertSame(BroadcastJobStage::RENDERING, $job->refresh()->stage);

            Queue::assertPushed(
                RenderBroadcastShotJob::class,
                static fn (RenderBroadcastShotJob $dispatched): bool => $dispatched->broadcastJobId === (string) $job->id
                    && $dispatched->shotId === (string) $shot1->id
            );
            $this->assertArrayHasKey((string) $shot2->id, $job->artifacts['rendered_shots'] ?? []);
        } finally {
            @unlink($existingClipPath);
        }
    }

    public function test_reassemble_after_edits_prunes_purged_rendered_clips(): void
    {
        Queue::fake();

        ['rundown' => $rundown, 'job' => $job, 'shot1' => $shot1, 'shot2' => $shot2] = $this->createRundownWithTwoShots();

        /** @var UpdateRundownShotsAction $action */
        $action = app(UpdateRundownShotsAction::class);
        $action->reassembleAfterEdits($rundown);

        $job->refresh();
        $this->assertSame([], $job->artifacts['rendered_shots'] ?? []);

        Queue::assertPushed(
            RenderBroadcastShotJob::class,
            static fn (RenderBroadcastShotJob $dispatched): bool => $dispatched->broadcastJobId === (string) $job->id
                && $dispatched->shotId === (string) $shot1->id
        );
    }

    public function test_livewire_tv_rundown_shots_modal_opens_and_renders_for_admin(): void
    {
        ['admin' => $admin, 'rundown' => $rundown, 'shot1' => $shot1] = $this->createRundownWithTwoShots();

        Livewire::actingAs($admin)
            ->test(TvRundownShotsModal::class)
            ->call('open', (string) $rundown->id)
            ->assertSet('isOpen', true)
            ->assertSet('descriptions.'.$shot1->id, 'Nueva escena inicial')
            ->assertSee('Escaleta Selectiva');
    }

    public function test_livewire_tv_rundown_shots_modal_forbids_non_admin_user(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $this->actingAs($regularUser);

        Livewire::test(TvRundownShotsModal::class)
            ->assertForbidden();
    }

    public function test_livewire_tv_rundown_shots_modal_warns_when_description_modified_without_regenerating(): void
    {
        Queue::fake();

        ['admin' => $admin, 'rundown' => $rundown, 'shot1' => $shot1] = $this->createRundownWithTwoShots();

        Livewire::actingAs($admin)
            ->test(TvRundownShotsModal::class)
            ->call('open', (string) $rundown->id)
            ->set('descriptions.'.$shot1->id, 'Descripción modificada sin regenerar')
            ->call('reassembleOnly')
            ->assertSet('isOpen', true)
            ->assertNotSet('errorMessage', null);

        Queue::assertNothingPushed();
    }

    public function test_admin_can_stream_rundown_shot_storyboard_for_other_owner_and_non_admin_is_forbidden(): void
    {
        Storage::fake('local');

        ['admin' => $admin, 'rundown' => $rundown, 'shot1' => $shot1] = $this->createRundownWithTwoShots();
        $systemOwner = User::factory()->create(['is_admin' => true]);
        $rundown->project->update(['user_id' => $systemOwner->id]);

        Storage::disk('local')->put('projects/test/shot1.png', 'PNG_DATA');

        /** @var UpdateRundownShotsAction $action */
        $action = app(UpdateRundownShotsAction::class);
        $shots = $action->loadEditableShots($rundown->refresh());
        $url = $shots[0]['storyboard_url'] ?? '';

        $this->assertStringContainsString('v=', $url);

        $this->actingAs($admin)
            ->get($url)
            ->assertOk();

        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($regularUser)
            ->get($url)
            ->assertForbidden();
    }

    /**
     * @return array{admin: User, rundown: TvRundown, job: BroadcastJob, shot1: Shot, shot2: Shot}
     */
    private function createRundownWithTwoShots(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->aiVideo()->create(['user_id' => $admin->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
        ]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
        ]);

        $shot1 = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PG',
            'description' => 'Nueva escena inicial',
            'storyboard_image_path' => 'projects/test/shot1.png',
        ]);

        $shot2 = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'PM',
            'description' => 'Segunda escena de estudio',
            'storyboard_image_path' => 'projects/test/shot2.png',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'tv-main',
            'format' => 'pill',
            'title' => 'Escaleta Selectiva',
            'raw_text' => 'Texto original uno. Texto original dos.',
            'language' => 'es',
            'visual_mode' => 'editorial_documentary_35mm',
            'stage' => BroadcastJobStage::COMPLETED,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'shots' => [
                    [
                        'shot_id' => (string) $shot1->id,
                        'shot_number' => 1,
                        'oral_text' => 'Texto original uno.',
                        'word_count' => 3,
                        'estimated_seconds' => 4,
                    ],
                    [
                        'shot_id' => (string) $shot2->id,
                        'shot_number' => 2,
                        'oral_text' => 'Texto original dos.',
                        'word_count' => 3,
                        'estimated_seconds' => 4,
                    ],
                ],
                'full_oral_text' => 'Texto original uno. Texto original dos.',
                'master_audio' => [
                    'audio_path' => 'broadcast/master.mp3',
                    'duration_seconds' => 12.0,
                    'timed_shots' => [
                        ['shot_id' => (string) $shot1->id, 'start_seconds' => 0.0, 'duration_seconds' => 6.0],
                        ['shot_id' => (string) $shot2->id, 'start_seconds' => 6.0, 'duration_seconds' => 6.0],
                    ],
                ],
                'shot_visuals' => [
                    (string) $shot1->id => 'projects/test/shot1.png',
                    (string) $shot2->id => 'projects/test/shot2.png',
                ],
                'rendered_shots' => [
                    (string) $shot1->id => [
                        'shot_id' => (string) $shot1->id,
                        'shot_number' => 1,
                        'clip_path' => 'broadcast/clips/shot1.mp4',
                        'duration_seconds' => 6.0,
                    ],
                    (string) $shot2->id => [
                        'shot_id' => (string) $shot2->id,
                        'shot_number' => 2,
                        'clip_path' => 'broadcast/clips/shot2.mp4',
                        'duration_seconds' => 6.0,
                    ],
                ],
            ],
        ]);

        $rundown = TvRundown::create([
            'project_id' => $project->id,
            'broadcast_job_id' => $job->id,
            'title' => 'Escaleta Selectiva',
            'source_type' => 'prompt',
            'format' => 'pill',
            'language' => 'es',
            'script_text' => 'Texto original uno. Texto original dos.',
            'visual_mode' => 'editorial_documentary_35mm',
            'status' => 'completed',
            'stage' => 'completed',
        ]);

        return [
            'admin' => $admin,
            'rundown' => $rundown,
            'job' => $job,
            'shot1' => $shot1,
            'shot2' => $shot2,
        ];
    }
}
