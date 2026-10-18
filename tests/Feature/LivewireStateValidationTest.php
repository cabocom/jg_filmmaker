<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectList;
use App\Livewire\Studio\ContextBibleDrawer;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireStateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_block_rejects_invalid_stage(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'stage' => 'raw']);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('editingBlockId', $block->id)
            ->set('editLockVersion', $block->lock_version)
            ->set('editStage', 'malicious_stage')
            ->call('saveBlock')
            ->assertHasErrors(['editStage']);

        $this->assertSame('raw', $block->fresh()->stage);
    }

    public function test_set_block_stage_ignores_unwhitelisted_stage(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'stage' => 'raw']);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('setBlockStage', $block->id, 'injected_stage');

        $this->assertSame('raw', $block->fresh()->stage);
    }

    public function test_create_new_block_rejects_oversized_raw_text(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $giantPayload = str_repeat('A', 50001);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('activeSceneId', $scene->id)
            ->set('newBlockRawText', $giantPayload)
            ->call('createNewBlock')
            ->assertHasErrors(['newBlockRawText']);

        $this->assertSame(0, $scene->scriptBlocks()->count());
    }

    public function test_project_list_rejects_unwhitelisted_status(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProjectList::class)
            ->set('title', 'Proyecto de Prueba')
            ->set('status', 'unauthorized_status')
            ->call('createProject')
            ->assertHasErrors(['status']);

        $this->assertSame(0, $user->projects()->count());
    }

    public function test_context_bible_rejects_duplicate_character_and_location_names(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        Character::factory()->create(['project_id' => $project->id, 'name' => 'Sara']);
        Location::factory()->create(['project_id' => $project->id, 'name' => 'CAFETERIA']);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->set('newCharName', 'Sara')
            ->call('createCharacter')
            ->assertHasErrors(['newCharName']);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->set('newLocName', 'CAFETERIA')
            ->set('newLocType', 'INT')
            ->call('createLocation')
            ->assertHasErrors(['newLocName']);
    }

    public function test_context_bible_recalculates_usages_on_server_even_if_client_manipulates_deleting_usages(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $char = Character::factory()->create(['project_id' => $project->id, 'name' => 'Marcos']);

        // Bloque que utiliza a Marcos en su texto
        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'screenplay_content' => "MARCOS\nHola a todos.",
        ]);

        $component = Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('requestDeleteCharacter', $char->id);

        $this->assertNotEmpty($component->get('deletingUsages'));

        // Intentar ejecutar confirmDelete()
        $component->call('confirmDelete');

        // El personaje no debe haber sido eliminado porque el servidor detectó el uso
        $this->assertDatabaseHas('characters', ['id' => $char->id]);
    }
}
