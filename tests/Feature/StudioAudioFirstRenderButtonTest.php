<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Livewire\Studio\ScriptEditor;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StudioAudioFirstRenderButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_renders_disabled_button_without_shots_and_enables_for_any_project_with_shots(): void
    {
        Queue::fake();
        config()->set('services.filmmaker.mock_tts', true);
        config()->set('services.jg_agente.mock', true);

        $user = User::factory()->create(['is_admin' => true]);

        $projectWithoutShots = Project::factory()->create([
            'user_id' => $user->id,
            'category' => ProjectCategory::PERSONAL,
            'title' => 'Sin Planos',
        ]);
        Scene::create([
            'project_id' => $projectWithoutShots->id,
            'scene_order' => 1,
            'slugline' => 'INT. CASA - DÍA',
            'time_of_day' => 'DÍA',
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $projectWithoutShots])
            ->assertSee('Disponible cuando el proyecto llegue a la etapa de Shots');

        $personalWithShots = Project::factory()->create([
            'user_id' => $user->id,
            'category' => ProjectCategory::PERSONAL,
            'title' => 'Corto Personal',
        ]);
        $scene = Scene::create([
            'project_id' => $personalWithShots->id,
            'scene_order' => 1,
            'slugline' => 'EXT. PARQUE - DÍA',
            'time_of_day' => 'DÍA',
        ]);
        $block = ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::SHOTS->value,
            'raw_text' => 'Primera frase del proyecto personal. Segunda frase de cierre.',
        ]);
        Shot::create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PG',
            'description' => 'Plano general del parque.',
        ]);

        TvRundown::create([
            'project_id' => $personalWithShots->id,
            'title' => 'Corto Personal',
            'source_type' => 'manual',
            'source_kind' => 'manual',
            'format' => 'pill',
            'type' => 'pill',
            'script_text' => 'Texto antiguo.',
            'stage' => 'draft',
            'status' => 'draft',
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $personalWithShots])
            ->assertSee('Render Vídeo')
            ->call('renderAudioFirstVideo')
            ->assertHasNoErrors();

        $job = BroadcastJob::query()->where('project_id', $personalWithShots->id)->first();
        $this->assertNotNull($job);
        $this->assertSame(BroadcastJobStage::RENDERING, $job->stage);
        $this->assertSame('Primera frase del proyecto personal. Segunda frase de cierre.', $job->raw_text);
        $this->assertSame(
            'Primera frase del proyecto personal. Segunda frase de cierre.',
            TvRundown::query()->where('project_id', $personalWithShots->id)->value('script_text')
        );
    }
}
