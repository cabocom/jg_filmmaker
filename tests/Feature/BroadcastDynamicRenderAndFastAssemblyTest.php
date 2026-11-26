<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AssembleBroadcastEpisodeAction;
use App\Actions\Broadcast\GenerateBroadcastVisualsBatchAction;
use App\Actions\Broadcast\RenderShotClipAction;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\MotionFilterBuilder;
use App\Services\Media\MediaStorageService;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcastDynamicRenderAndFastAssemblyTest extends TestCase
{
    use RefreshDatabase;

    public function test_motion_filter_builder_generates_full_bleed_crop_and_ken_burns_without_pad(): void
    {
        $builder = app(MotionFilterBuilder::class);

        foreach (['PG', 'PP', 'PM'] as $shotType) {
            $filter = $builder->buildImageMotionFilter($shotType, 5.0, 1920, 1080);

            $this->assertStringContainsString('force_original_aspect_ratio=increase,crop=', $filter);
            $this->assertStringContainsString('zoompan=', $filter);
            $this->assertStringContainsString('fps=30', $filter);
            $this->assertStringNotContainsString('pad=', $filter);
        }
    }

    public function test_generate_broadcast_visuals_batch_action_reuses_existing_images_and_generates_missing(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);
        $scene = Scene::factory()->for($project)->create(['scene_order' => 1]);
        $block = ScriptBlock::factory()->for($scene)->create(['block_order' => 1]);

        $existingPath = 'storyboards/existing_shot_1.png';
        Storage::disk('local')->put($existingPath, 'FAKE_PNG_CONTENT');

        $shot1 = Shot::factory()->for($scene)->create([
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PG',
            'storyboard_image_path' => $existingPath,
        ]);

        $shot2 = Shot::factory()->for($scene)->create([
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'PM',
            'storyboard_image_path' => null,
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-batch-visuals',
            'format' => 'news',
            'title' => 'Batch Visuals Test',
            'raw_text' => 'Primera oración. Segunda oración.',
            'language' => 'es',
            'sources' => [],
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'shots' => [
                    ['shot_id' => $shot1->id, 'shot_number' => 1],
                    ['shot_id' => $shot2->id, 'shot_number' => 2],
                ],
            ],
        ]);

        try {
            $visuals = app(GenerateBroadcastVisualsBatchAction::class)->execute($job);

            $this->assertArrayHasKey((string) $shot1->id, $visuals);
            $this->assertArrayHasKey((string) $shot2->id, $visuals);
            $this->assertSame(Storage::disk('local')->path($existingPath), $visuals[(string) $shot1->id]);
            $this->assertFileExists($visuals[(string) $shot2->id]);

            $persisted = $job->fresh()?->artifacts['shot_visuals'] ?? [];
            $this->assertSame($visuals, $persisted);
        } finally {
            File::deleteDirectory(storage_path("app/private/broadcast/{$job->id}"));
        }
    }

    public function test_render_shot_clip_skips_per_shot_tts_when_master_audio_is_active(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);
        $scene = Scene::factory()->for($project)->create(['scene_order' => 1]);
        $block = ScriptBlock::factory()->for($scene)->create(['block_order' => 1]);
        $shot = Shot::factory()->for($scene)->create([
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
        ]);

        $tempWavPath = storage_path('app/private/test_master_audio_'.uniqid().'.wav');
        File::ensureDirectoryExists(dirname($tempWavPath));
        file_put_contents($tempWavPath, base64_decode('UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='));

        $ttsSpy = $this->spy(TextToSpeechService::class);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-skip-tts',
            'format' => 'news',
            'title' => 'Skip TTS Test',
            'raw_text' => 'Texto de prueba para el plano.',
            'language' => 'es',
            'sources' => [],
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::SHOTS,
            'project_id' => $project->id,
            'script_block_id' => $block->id,
            'artifacts' => [
                'master_audio' => [
                    'audio_path' => $tempWavPath,
                    'total_duration_seconds' => 8.5,
                ],
            ],
        ]);

        try {
            $rendered = app(RenderShotClipAction::class)->execute($job, $shot, [
                'oral_text' => 'Texto de prueba para el plano.',
                'duration_seconds' => 8.5,
                'shot_type' => 'PM',
            ]);

            $ttsSpy->shouldNotHaveReceived('synthesize');
            $this->assertNull($rendered['audio_path']);
            $this->assertNull($rendered['ass_path']);
            $this->assertSame(8.5, $rendered['duration']);
            $this->assertSame(8.5, $rendered['duration_seconds']);
            $this->assertFileExists((string) $rendered['clip_path']);
        } finally {
            @unlink($tempWavPath);
            File::deleteDirectory(storage_path("app/private/broadcast/{$job->id}"));
        }
    }

    public function test_assemble_episode_respects_conditional_podcast_and_short_flags(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'category' => ProjectCategory::AI_VIDEO->value,
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ch-conditional-exports',
            'format' => 'news',
            'title' => 'Conditional Exports Test',
            'raw_text' => 'Texto de prueba.',
            'language' => 'es',
            'sources' => [],
            'visual_mode' => 'static_card',
            'stage' => BroadcastJobStage::RENDERING,
            'project_id' => $project->id,
            'artifacts' => [
                'publish_to_podcast' => false,
                'generate_short' => false,
                'rendered_shots' => [
                    'shot-1' => [
                        'shot_id' => 'shot-1',
                        'shot_number' => 1,
                        'oral_text' => 'Texto de prueba.',
                        'duration' => 10.0,
                        'clip_path' => storage_path('app/private/broadcast/mock_shot_1.mp4'),
                    ],
                ],
            ],
        ]);

        try {
            $updated = app(AssembleBroadcastEpisodeAction::class)->execute($job);

            $mediaStorage = app(MediaStorageService::class);
            $basePath = "broadcast/{$job->channel_id}/{$job->id}";

            $this->assertTrue($mediaStorage->exists("{$basePath}/episode_1080p.mp4"));
            $this->assertFalse($mediaStorage->exists("{$basePath}/episode_podcast.mp3"));
            $this->assertFalse($mediaStorage->exists("{$basePath}/episode_short_9x16.mp4"));

            $exports = $updated->artifacts['exports'] ?? [];
            $this->assertSame("{$basePath}/episode_1080p.mp4", $exports['episode_1080p'] ?? null);
            $this->assertArrayHasKey('episode_podcast_mp3', $exports);
            $this->assertNull($exports['episode_podcast_mp3']);
            $this->assertArrayHasKey('episode_short_9x16', $exports);
            $this->assertNull($exports['episode_short_9x16']);
        } finally {
            File::deleteDirectory(storage_path("app/private/broadcast/{$job->id}"));
        }
    }

    public function test_assemble_episode_builds_audio_first_ffmpeg_commands_for_master_and_short(): void
    {
        $tempDir = storage_path('app/private/test_ffmpeg_cmds_'.uniqid());
        File::ensureDirectoryExists($tempDir);
        $masterAudioPath = "{$tempDir}/master audio:1.wav";
        $globalAssPath = "{$tempDir}/global subtitles:1.ass";
        $shortAssPath = "{$tempDir}/short subtitles:1.ass";
        file_put_contents($masterAudioPath, 'WAV');
        file_put_contents($globalAssPath, 'ASS');
        file_put_contents($shortAssPath, 'ASS');

        try {
            $action = app(AssembleBroadcastEpisodeAction::class);

            $masterCmd = $action->buildMasterAudioConcatCommand(
                "{$tempDir}/concat.txt",
                $masterAudioPath,
                "{$tempDir}/1080p.mp4",
                [
                    'resolution_profile' => '720p30',
                    'master_audio' => ['audio_path' => $masterAudioPath, 'ass_path' => $globalAssPath],
                ]
            );
            $this->assertContains($masterAudioPath, $masterCmd);
            $this->assertContains('1:a:0', $masterCmd);
            $this->assertContains('veryfast', $masterCmd);
            $vfIdx = array_search('-vf', $masterCmd, true);
            $this->assertNotFalse($vfIdx);
            $this->assertStringContainsString('\\:', $masterCmd[$vfIdx + 1]);
            $this->assertStringContainsString('\\ ', $masterCmd[$vfIdx + 1]);

            $shortAudioFirstCmd = $action->buildShort9x16Command(
                "{$tempDir}/short_list.txt",
                $shortAssPath,
                "{$tempDir}/short.mp4",
                ['master_audio' => ['audio_path' => $masterAudioPath]]
            );
            $this->assertContains($masterAudioPath, $shortAudioFirstCmd);
            $this->assertContains('1:a:0', $shortAudioFirstCmd);
            $this->assertContains('-shortest', $shortAudioFirstCmd);

            $shortLegacyCmd = $action->buildShort9x16Command(
                "{$tempDir}/short_list.txt",
                $shortAssPath,
                "{$tempDir}/short_legacy.mp4",
                []
            );
            $this->assertNotContains('1:a:0', $shortLegacyCmd);
            $this->assertContains('aac', $shortLegacyCmd);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
