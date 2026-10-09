<?php

namespace Tests\Feature;

use App\Livewire\Studio\ContextBibleDrawer;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireScriptEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_editor_renders_scene_blocks_and_shot_breakdown(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'El Susurro del Viento']);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'BAR CENTRAL', 'type' => 'INT']);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. BAR CENTRAL - NOCHE 1',
            'location_id' => $location->id,
            'time_of_day' => 'NOCHE',
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Javier está sentado solo en el bar.',
            'structured_text' => 'Javier apoya el vaso en la barra.',
            'screenplay_content' => "INT. BAR CENTRAL - NOCHE 1\n\nJAVIER apoya el vaso.\n\nJAVIER\nYa están aquí.",
            'user_locks' => [
                'locked_fields' => ['dialogue_speaker'],
                'pinned_terms' => ['Javi' => 'Javier'],
            ],
            'stage' => 'screenplay',
        ]);

        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'script_block_id' => $block->id,
            'shot_number' => 1,
            'shot_type' => 'PM',
            'description' => 'Plano medio de Javier apoyando el vaso.',
        ]);

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
            'editorial_notes' => 'Toma aprobada para corte definitivo.',
        ]);

        $response = $this->actingAs($user)->get(route('projects.show', ['project' => $project->id]));

        $response->assertStatus(200);
        $response->assertSee('INT. BAR CENTRAL - NOCHE 1');
        $response->assertSee('BAR CENTRAL');
        $response->assertSee('BLOQUE #1');
        $response->assertSee('Términos Fijos:');
        $response->assertSee('Javi ➜ Javier');
        $response->assertSee('--- shot 1 (PM) ---');
        $response->assertSee('TOMA 1');
        $response->assertSee('TOMA BUENA');
    }

    public function test_can_toggle_good_take_status_interactively(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        $take = Take::factory()->create(['shot_id' => $shot->id, 'is_good_take' => false]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('toggleGoodTake', $take->id);

        $this->assertTrue($take->fresh()->is_good_take);
    }

    public function test_context_bible_drawer_handles_character_editing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Javier',
            'costume_notes' => 'Gabardina gris',
        ]);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('open')
            ->assertSet('isOpen', true)
            ->call('startEditCharacter', $character->id)
            ->set('editingCharCostume', 'Chaqueta de cuero oscura')
            ->call('saveCharacter')
            ->assertHasNoErrors();

        $this->assertEquals('Chaqueta de cuero oscura', $character->fresh()->costume_notes);
    }
}
