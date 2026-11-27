<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Broadcast\SyncBroadcastProjectBibleAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Enums\BlockStage;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\User;
use App\Services\JgAgente\AgentClientService;
use App\Services\Storyboard\BroadcastFramePromptBuilder;
use App\Services\Storyboard\StoryboardPromptBuilder;
use App\Services\StoryboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BroadcastPillVisualNoPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pill_format_does_not_seed_default_presenter_or_studio_slugline(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->once()->andReturn([]);

        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-pill',
            'format' => 'pill',
            'title' => 'Píldora Redes Neuronales',
            'raw_text' => 'Arquitectura de redes neuronales en la nube y procesamiento distribuido.',
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

    public function test_broadcast_frame_prompt_builder_strips_host_locutor_prefixes_and_skips_presenter_in_pill(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->aiVideo()->create([
            'user_id' => $user->id,
            'storyboard_style' => 'editorial_documentary_35mm',
        ]);

        TvRundown::create([
            'project_id' => $project->id,
            'title' => 'Píldora Cloud',
            'format' => 'pill',
            'source_type' => 'prompt',
            'status' => 'draft',
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'EXT. DOCUMENTAL TEMÁTICO - DÍA',
            'time_of_day' => 'DÍA',
        ]);

        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Texto de píldora.',
            'stage' => BlockStage::SHOTS->value,
        ]);

        $shotHost = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'HOST (V.O.): Arquitectura de redes neuronales en la nube.',
        ]);

        $shotLocutor = Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 2,
            'shot_type' => 'PG',
            'description' => 'LOCUTOR: Un servidor procesa datos.',
        ]);

        $builder = new BroadcastFramePromptBuilder(
            new StoryboardPromptBuilder(StoryboardService::STYLES),
            StoryboardService::STYLES
        );
        $config = VisualAestheticConfigDTO::fromArray([], VisualAestheticConfigDTO::MODE_FULL_BLEED);

        $promptHost = $builder->buildPrompt($shotHost, 'editorial_documentary_35mm', $config);
        $promptLocutor = $builder->buildPrompt($shotLocutor, 'editorial_documentary_35mm', $config);

        foreach ([$promptHost, $promptLocutor] as $prompt) {
            $this->assertStringNotContainsString('HOST', $prompt);
            $this->assertStringNotContainsString('LOCUTOR', $prompt);
            $this->assertStringNotContainsString('Presentador', $prompt);
            $this->assertStringNotContainsString('Character Consistency', $prompt);
        }

        $this->assertStringContainsString('Arquitectura de redes neuronales en la nube', $promptHost);
        $this->assertStringContainsString('Un servidor procesa datos', $promptLocutor);
    }

    public function test_news_format_still_seeds_default_presenter_and_studio_slugline(): void
    {
        Queue::fake();

        $mockAgent = Mockery::mock(AgentClientService::class);
        $this->app->instance(AgentClientService::class, $mockAgent);

        $mockAgent->shouldReceive('getLastTelemetry')->andReturn(['model' => 'test-llm']);
        $mockAgent->shouldReceive('extractCharacters')->once()->andReturn([]);

        $dto = BroadcastJobRequestDTO::fromArray([
            'channel_id' => 'ch-news',
            'format' => 'news',
            'title' => 'Informativo Tecnológico',
            'raw_text' => 'Resumen diario de infraestructura y seguridad informática.',
        ]);

        $job = app(AutoGenerateBroadcastShotsAction::class)->initialize($dto);
        $summary = app(SyncBroadcastProjectBibleAction::class)->execute($job);

        $project = $job->project->fresh(['characters', 'locations', 'scenes']);
        $scene = $project->scenes->first();

        $this->assertNotNull($scene);
        $this->assertSame('INT. ESTUDIO BROADCAST - DÍA', $scene->slugline);
        $this->assertContains('Presentador', $summary['characters']);
        $this->assertDatabaseHas('characters', [
            'project_id' => $project->id,
            'name' => 'Presentador',
        ]);
    }
}
