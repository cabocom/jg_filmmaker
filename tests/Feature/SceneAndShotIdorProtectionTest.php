<?php

namespace Tests\Feature;

use App\Actions\Studio\ManageScriptStructureAction;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SceneAndShotIdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected User $otherUser;

    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'author@example.com']);
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Proyecto Principal',
        ]);

        $this->otherUser = User::factory()->create(['email' => 'victim@example.com']);
        $this->otherProject = Project::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Proyecto Víctima',
        ]);
    }

    public function test_create_scene_rejects_cross_tenant_location_id(): void
    {
        $otherLocation = Location::factory()->create([
            'project_id' => $this->otherProject->id,
            'name' => 'BUNKER SECRETO VÍCTIMA',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->set('newSceneSlugline', 'INT. NUEVA ESCENA - DÍA')
            ->set('newSceneTimeOfDay', 'DÍA')
            ->set('newSceneLocationId', $otherLocation->id)
            ->call('createScene')
            ->assertHasErrors(['newSceneLocationId']);

        $this->assertDatabaseMissing('scenes', [
            'project_id' => $this->project->id,
            'location_id' => $otherLocation->id,
        ]);
    }

    public function test_create_scene_accepts_valid_project_location_id(): void
    {
        $ownLocation = Location::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'SALÓN PROPIO',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->set('newSceneSlugline', 'INT. SALÓN PROPIO - NOCHE')
            ->set('newSceneTimeOfDay', 'NOCHE')
            ->set('newSceneLocationId', $ownLocation->id)
            ->call('createScene')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('scenes', [
            'project_id' => $this->project->id,
            'location_id' => $ownLocation->id,
            'slugline' => 'INT. SALÓN PROPIO - NOCHE',
        ]);
    }

    public function test_update_scene_rejects_cross_tenant_location_id(): void
    {
        $scene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'scene_order' => 1,
            'slugline' => 'INT. HABITACIÓN - DÍA',
        ]);

        $otherLocation = Location::factory()->create([
            'project_id' => $this->otherProject->id,
            'name' => 'MANSIÓN PRIVADA',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->call('openEditSceneModal', $scene->id)
            ->set('editingSceneLocationId', $otherLocation->id)
            ->call('updateScene')
            ->assertHasErrors(['editingSceneLocationId']);

        $scene->refresh();
        $this->assertNotEquals($otherLocation->id, $scene->location_id);
    }

    public function test_add_shot_rejects_cross_tenant_or_other_scene_script_block_id(): void
    {
        $scene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'scene_order' => 1,
            'slugline' => 'EXT. CALLE - DÍA',
        ]);

        $otherScene = Scene::factory()->create([
            'project_id' => $this->otherProject->id,
            'scene_order' => 1,
            'slugline' => 'INT. NAVE ALIENÍGENA - NOCHE',
        ]);

        $otherBlock = ScriptBlock::factory()->create([
            'scene_id' => $otherScene->id,
            'block_order' => 1,
            'raw_text' => 'Texto privado de otra víctima',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->set('activeSceneId', $scene->id)
            ->set('newShotType', 'PM')
            ->set('newShotDescription', 'Plano medio de Marcus')
            ->set('newShotBlockId', $otherBlock->id)
            ->call('addShot')
            ->assertHasErrors(['newShotBlockId']);

        $this->assertDatabaseMissing('shots', [
            'scene_id' => $scene->id,
            'script_block_id' => $otherBlock->id,
        ]);
    }

    public function test_add_shot_accepts_valid_scene_script_block_id(): void
    {
        $scene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'scene_order' => 1,
            'slugline' => 'EXT. CALLE - DÍA',
        ]);

        $ownBlock = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Bloque propio de la escena',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScriptEditor::class, ['project' => $this->project])
            ->set('activeSceneId', $scene->id)
            ->set('newShotType', 'PP')
            ->set('newShotDescription', 'Primer plano de detalle')
            ->set('newShotBlockId', $ownBlock->id)
            ->call('addShot')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('shots', [
            'scene_id' => $scene->id,
            'script_block_id' => $ownBlock->id,
            'shot_type' => 'PP',
        ]);
    }

    public function test_manage_script_structure_action_nullifies_foreign_location_id_as_defense_in_depth(): void
    {
        $otherLocation = Location::factory()->create([
            'project_id' => $this->otherProject->id,
            'name' => 'FOREIGN LOCATION',
        ]);

        $action = app(ManageScriptStructureAction::class);

        $createdScene = $action->createScene($this->project, [
            'slugline' => 'INT. PRUEBA ACCION - DÍA',
            'time_of_day' => 'DÍA',
            'location_id' => $otherLocation->id,
        ]);

        $this->assertNull($createdScene->location_id);

        $action->updateScene($createdScene, [
            'slugline' => 'INT. PRUEBA ACCION UPDATE - DÍA',
            'time_of_day' => 'DÍA',
            'location_id' => $otherLocation->id,
        ]);

        $createdScene->refresh();
        $this->assertNull($createdScene->location_id);
    }
}
