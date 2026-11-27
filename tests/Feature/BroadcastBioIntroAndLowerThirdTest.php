<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\SyncBroadcastProjectBibleAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Livewire\Tv\TvRundownsPanel;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\TvVideo;
use App\Models\User;
use App\Services\Broadcast\BroadcastSeriesHelper;
use App\Services\Broadcast\ShotSubtitleGenerator;
use App\Services\JgAgente\AgentClientService;
use App\Services\Storyboard\BroadcastFramePromptBuilder;
use App\Services\Storyboard\StoryboardPromptBuilder;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class BroadcastBioIntroAndLowerThirdTest extends TestCase
{
    use RefreshDatabase;

    public function test_bio_format_uses_documentary_pipeline_without_presenter_and_music_is_disabled_in_rundowns(): void
    {
        Queue::fake();

        $adminUser = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($adminUser)
            ->test(TvRundownsPanel::class)
            ->set('sourceKind', 'manual')
            ->set('rundownType', 'bio')
            ->set('title', 'Biografía Ada Lovelace')
            ->set('scriptText', 'Ada Lovelace diseñó el primer algoritmo para una máquina analítica.')
            ->call('createRundown')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tv_rundowns', [
            'title' => 'Biografía Ada Lovelace',
            'type' => 'bio',
            'format' => 'bio',
        ]);

        Livewire::actingAs($adminUser)
            ->test(TvRundownsPanel::class)
            ->set('sourceKind', 'manual')
            ->set('rundownType', 'music')
            ->set('title', 'Tema Musical Inválido')
            ->set('scriptText', 'Contenido musical no permitido en escaletas IA.')
            ->call('createRundown')
            ->assertHasErrors(['rundownType']);

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->once()->andReturn([]);

        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-bio',
            'format' => 'bio',
            'title' => 'Biografía Ada Lovelace',
            'raw_text' => 'Ada Lovelace diseñó el primer algoritmo para una máquina analítica.',
        ]);

        $job = app(AutoGenerateBroadcastShotsAction::class)->initialize($dto);
        $summary = app(SyncBroadcastProjectBibleAction::class)->execute($job);

        $project = $job->project->fresh(['characters', 'locations', 'scenes']);
        $scene = $project->scenes->first();

        $this->assertNotNull($scene);
        $this->assertSame('EXT. DOCUMENTAL TEMÁTICO - DÍA', $scene->slugline);
        $this->assertSame(0, $project->characters()->count());
        $this->assertSame(0, $summary['characters_count']);
        $this->assertDatabaseMissing('characters', [
            'project_id' => $project->id,
            'name' => 'Presentador',
        ]);
    }

    public function test_broadcast_frame_prompt_builder_enforces_no_text_directives_for_bio_and_other_formats(): void
    {
        $user = User::factory()->create();
        $builder = new BroadcastFramePromptBuilder(
            new StoryboardPromptBuilder(StoryboardService::STYLES),
            StoryboardService::STYLES
        );
        $config = VisualAestheticConfigDTO::fromArray([], VisualAestheticConfigDTO::MODE_FULL_BLEED);

        foreach (['bio', 'news'] as $format) {
            $project = Project::factory()->aiVideo()->create([
                'user_id' => $user->id,
                'storyboard_style' => 'editorial_documentary_35mm',
            ]);

            TvRundown::create([
                'project_id' => $project->id,
                'title' => "Episodio {$format}",
                'format' => $format,
                'type' => $format,
                'source_type' => 'prompt',
                'status' => 'draft',
            ]);

            $scene = Scene::create([
                'project_id' => $project->id,
                'scene_order' => 1,
                'slugline' => $format === 'bio' ? 'EXT. DOCUMENTAL TEMÁTICO - DÍA' : 'INT. ESTUDIO BROADCAST - DÍA',
                'time_of_day' => 'DÍA',
            ]);

            $block = ScriptBlock::create([
                'scene_id' => $scene->id,
                'block_order' => 1,
                'raw_text' => 'Texto de prueba.',
                'stage' => BlockStage::SHOTS->value,
            ]);

            $shot = Shot::create([
                'scene_id' => $scene->id,
                'script_block_id' => $block->id,
                'shot_number' => 1,
                'shot_type' => 'PM',
                'description' => 'Engranajes mecánicos de latón iluminados por luz natural de taller.',
            ]);

            $prompt = $builder->buildPrompt($shot, 'editorial_documentary_35mm', $config);

            $this->assertStringContainsString('STRICTLY NO text, NO words, NO letters', $prompt);
            $this->assertStringContainsString('Avoid: any embedded text, letters, words, logos, captions, signs, or watermarks in the image.', $prompt);
        }
    }

    public function test_series_helper_and_subtitle_generator_build_lower_third_with_series_title_url_and_credit(): void
    {
        $this->assertSame('Breves Biografías IA', BroadcastSeriesHelper::seriesNameForFormat('bio'));

        $lowerThird = BroadcastSeriesHelper::buildLowerThirdText(
            'bio',
            'Ada Lovelace',
            'https://es.wikipedia.org/wiki/Ada_Lovelace',
            'Fuente: Wikipedia · CC BY-SA 4.0',
        );

        $this->assertSame(
            'Breves Biografías IA · Ada Lovelace\Nhttps://es.wikipedia.org/wiki/Ada_Lovelace · Fuente: Wikipedia · CC BY-SA 4.0',
            $lowerThird
        );

        $assPath = storage_path('app/private/test_lower_third_'.uniqid().'.ass');

        try {
            $generator = new ShotSubtitleGenerator;
            $generator->generateGlobalAss([
                [
                    'start_seconds' => 0.0,
                    'duration_seconds' => 4.0,
                    'oral_text' => 'Ada Lovelace anticipó el potencial universal de la computación.',
                ],
            ], $assPath, $lowerThird);

            $this->assertFileExists($assPath);
            $assContent = (string) file_get_contents($assPath);

            $this->assertStringContainsString('Style: Credit,', $assContent);
            $this->assertStringContainsString(',1,36,36,24,1', $assContent);
            $this->assertStringContainsString('Breves Biografías IA', $assContent);
            $this->assertStringContainsString('Ada Lovelace', $assContent);
            $this->assertStringContainsString('https://es.wikipedia.org/wiki/Ada_Lovelace', $assContent);
            $this->assertStringContainsString('Fuente: Wikipedia · CC BY-SA 4.0', $assContent);
        } finally {
            @unlink($assPath);
        }
    }

    public function test_migration_moves_existing_pill_records_to_bio(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->aiVideo()->create(['user_id' => $user->id]);

        $rundown = TvRundown::create([
            'project_id' => $project->id,
            'channel_slug' => 'ai-today',
            'title' => 'Antigua Píldora',
            'format' => 'pill',
            'type' => 'pill',
            'source_type' => 'prompt',
            'source_kind' => 'manual',
            'status' => 'draft',
            'stage' => 'draft',
        ]);

        $job = BroadcastJob::create([
            'channel_id' => 'ai-today',
            'format' => 'pill',
            'title' => 'Antigua Píldora Job',
            'raw_text' => 'Texto de prueba.',
            'language' => 'es',
            'sources' => [],
            'visual_mode' => 'storyboard',
            'stage' => BroadcastJobStage::RAW,
            'project_id' => $project->id,
        ]);

        $video = TvVideo::create([
            'project_id' => $project->id,
            'rundown_id' => $rundown->id,
            'broadcast_job_id' => $job->id,
            'channel_slug' => 'ai-today',
            'type' => 'pill',
            'title' => 'Antigua Píldora Video',
            'stage' => 'completed',
            'status' => 'ready',
        ]);

        $migrationFile = collect(glob(database_path('migrations/*.php')) ?: [])
            ->first(static function (string $path): bool {
                $contents = (string) file_get_contents($path);

                return str_contains($contents, 'tv_rundowns')
                    && str_contains($contents, 'tv_videos')
                    && str_contains($contents, 'broadcast_jobs')
                    && str_contains($contents, "'pill'")
                    && str_contains($contents, "'bio'");
            });

        $this->assertNotNull($migrationFile);

        $migration = require $migrationFile;
        $migration->up();

        $rundown->refresh();
        $video->refresh();
        $job->refresh();

        $this->assertSame('bio', $rundown->format);
        $this->assertSame('bio', $rundown->type);
        $this->assertSame('bio', $video->type);
        $this->assertSame('bio', $job->format);
    }
}
