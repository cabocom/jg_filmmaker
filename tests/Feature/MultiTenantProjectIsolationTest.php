<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiTenantProjectIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_own_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('projects.show', $project));

        $response->assertStatus(200);
    }

    public function test_other_user_cannot_access_project_editor_and_receives_403_forbidden(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('projects.show', $project));

        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_other_user_project_editor_under_strict_privacy(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->get(route('projects.show', $project));

        $response->assertStatus(403);
    }

    public function test_script_editor_actions_reject_cross_tenant_block_access(): void
    {
        $ownerA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $ownerA->id]);
        $sceneA = Scene::factory()->create(['project_id' => $projectA->id]);
        $blockA = ScriptBlock::factory()->create(['scene_id' => $sceneA->id]);

        $ownerB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $ownerB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id]);
        $blockB = ScriptBlock::factory()->create(['scene_id' => $sceneB->id, 'raw_text' => 'Guion confidencial B']);

        // El usuario A, interactuando en su proyecto A, intenta acceder al bloque confidencial del proyecto B
        Livewire::actingAs($ownerA)
            ->test(ScriptEditor::class, ['project' => $projectA])
            ->call('openBlockDetails', $blockB->id)
            ->assertStatus(404);
    }

    public function test_script_editor_actions_reject_cross_tenant_take_access(): void
    {
        $ownerA = User::factory()->create();
        $projectA = Project::factory()->create(['user_id' => $ownerA->id]);

        $ownerB = User::factory()->create();
        $projectB = Project::factory()->create(['user_id' => $ownerB->id]);
        $sceneB = Scene::factory()->create(['project_id' => $projectB->id]);
        $shotB = Shot::factory()->create(['scene_id' => $sceneB->id]);
        $takeB = Take::factory()->create([
            'shot_id' => $shotB->id,
            'is_good_take' => false,
            'editorial_notes' => 'Toma secreta proyecto B',
        ]);

        // El usuario A intenta marcar como buena una toma de otro proyecto
        Livewire::actingAs($ownerA)
            ->test(ScriptEditor::class, ['project' => $projectA])
            ->call('toggleGoodTake', $takeB->id)
            ->assertStatus(404);

        $this->assertFalse($takeB->fresh()->is_good_take);
    }
}
