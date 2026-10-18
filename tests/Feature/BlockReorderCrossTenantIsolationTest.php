<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlockReorderCrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_steal_or_move_block_from_another_project_into_own_scene(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);
        $sceneA = Scene::factory()->create(['project_id' => $projectA->id, 'scene_order' => 1]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id, 'scene_order' => 1]);
        $blockB = ScriptBlock::factory()->create([
            'scene_id' => $sceneB->id,
            'block_order' => 1,
            'raw_text' => 'Texto confidencial del proyecto B',
        ]);

        // El usuario A intenta mover el bloque de B a su propia escena A
        Livewire::actingAs($userA)
            ->test(ScriptEditor::class, ['project' => $projectA])
            ->call('reorderBlock', $blockB->id, 1, $sceneA->id)
            ->assertStatus(404);

        // Comprobar que el bloque de B sigue intacto en su escena original
        $blockB->refresh();
        $this->assertEquals($sceneB->id, $blockB->scene_id);
        $this->assertEquals(1, $blockB->block_order);
        $this->assertEquals('Texto confidencial del proyecto B', $blockB->raw_text);
    }

    public function test_cannot_move_own_block_to_scene_of_another_project(): void
    {
        $userA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $userA->id]);
        $sceneA = Scene::factory()->create(['project_id' => $projectA->id, 'scene_order' => 1]);
        $blockA = ScriptBlock::factory()->create([
            'scene_id' => $sceneA->id,
            'block_order' => 1,
            'raw_text' => 'Bloque A',
        ]);

        $userB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $userB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id, 'scene_order' => 1]);

        // El usuario A intenta mover su bloque a la escena del proyecto B
        Livewire::actingAs($userA)
            ->test(ScriptEditor::class, ['project' => $projectA])
            ->call('reorderBlock', $blockA->id, 1, $sceneB->id)
            ->assertStatus(404);

        $blockA->refresh();
        $this->assertEquals($sceneA->id, $blockA->scene_id);
    }

    public function test_reorder_block_between_own_scenes_updates_associated_shots_and_reindexes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $scene1 = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $scene2 = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 2]);

        $block1 = ScriptBlock::factory()->create(['scene_id' => $scene1->id, 'block_order' => 1]);
        $block2 = ScriptBlock::factory()->create(['scene_id' => $scene1->id, 'block_order' => 2]);
        $shot = Shot::factory()->create(['scene_id' => $scene1->id, 'script_block_id' => $block1->id, 'shot_number' => 1]);

        $block3 = ScriptBlock::factory()->create(['scene_id' => $scene2->id, 'block_order' => 1]);

        // Mover block1 de scene1 a scene2 en posición 1
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('reorderBlock', $block1->id, 1, $scene2->id)
            ->assertHasNoErrors();

        // 1. El bloque debe haber cambiado de escena
        $block1->refresh();
        $this->assertEquals($scene2->id, $block1->scene_id);
        $this->assertEquals(1, $block1->block_order);

        // 2. El plano asociado al bloque debe haber actualizado su scene_id
        $shot->refresh();
        $this->assertEquals($scene2->id, $shot->scene_id);

        // 3. El bloque restante de scene1 debe haberse reindexado a orden 1
        $block2->refresh();
        $this->assertEquals(1, $block2->block_order);

        // 4. El bloque previo de scene2 debe haberse desplazado a orden 2
        $block3->refresh();
        $this->assertEquals(2, $block3->block_order);
    }
}
