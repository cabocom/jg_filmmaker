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
        $response->assertSee('Detalles');
        $response->assertSee('Javier está sentado solo en el bar.');

        // Al conmutar interactivamente a la pestaña de Planos y abrir el drawer de detalles, se renderiza el desglose técnico y tomas
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id, 'raw')
            ->assertSee('BLOQUE #1')
            ->assertSee('Términos Fijos:')
            ->assertSee('Javi ➜ Javier')
            ->call('setBlockTab', $block->id, 'shots')
            ->assertSee('--- shot 1 (PM) ---')
            ->assertSee('TOMA 1')
            ->assertSee('TOMA BUENA');
    }

    public function test_can_remove_pinned_term_from_block(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'user_locks' => [
                'pinned_terms' => ['Javi' => 'Javier'],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('removePinnedTerm', $block->id, 'Javi');

        $freshLocks = $block->fresh()->user_locks ?? [];
        $this->assertArrayNotHasKey('Javi', $freshLocks['pinned_terms'] ?? []);
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

    public function test_context_bible_drawer_blocks_character_deletion_when_in_use_and_lists_occurrences(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 2,
            'raw_text' => 'Marcos entra apresurado en la estancia.',
        ]);

        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Marcos',
        ]);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('open')
            ->call('requestDeleteCharacter', $character->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingType', 'character')
            ->assertSet('deletingName', 'Marcos')
            ->assertSet('deletingUsages', ['ESC. 1 · BLOQUE 2'])
            ->assertSee('No se puede eliminar el personaje')
            ->assertSee('ESC. 1 · BLOQUE 2')
            ->assertSee('Ver detalle')
            ->call('inspectUsage', 'ESC. 1 · BLOQUE 2')
            ->assertSet('showDeleteModal', false)
            ->assertSet('isOpen', false)
            ->assertDispatched('open-block-details', blockId: $block->id);

        $this->assertDatabaseHas('characters', ['id' => $character->id]);
    }

    public function test_context_bible_drawer_allows_character_deletion_when_unused(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create([
            'project_id' => $project->id,
            'name' => 'Elena',
        ]);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('open')
            ->call('requestDeleteCharacter', $character->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingUsages', [])
            ->assertSee('¿Eliminar personaje permanentemente?')
            ->assertSee('Eliminar definitivamente')
            ->call('confirmDelete')
            ->assertDispatched('bible-updated')
            ->assertSee('eliminado correctamente de la Biblia.');

        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }

    public function test_context_bible_drawer_blocks_location_deletion_when_assigned_or_mentioned(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'Azotea']);
        Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 3,
            'location_id' => $location->id,
        ]);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('open')
            ->call('requestDeleteLocation', $location->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingType', 'location')
            ->assertSet('deletingName', 'Azotea')
            ->assertSet('deletingUsages', ['ESC. 3 (Asignada a la escena)'])
            ->assertSee('No se puede eliminar la localización')
            ->assertSee('ESC. 3 (Asignada a la escena)')
            ->call('confirmDelete')
            ->assertSee('No se puede eliminar: el elemento está siendo utilizado en el guión.');

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_context_bible_drawer_allows_location_deletion_when_unused(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $location = Location::factory()->create(['project_id' => $project->id, 'name' => 'Sótano Olvidado']);

        Livewire::actingAs($user)
            ->test(ContextBibleDrawer::class, ['project' => $project])
            ->call('open')
            ->call('requestDeleteLocation', $location->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingUsages', [])
            ->assertSee('¿Eliminar localización permanentemente?')
            ->call('confirmDelete')
            ->assertDispatched('bible-updated')
            ->assertSee('eliminada correctamente de la Biblia.');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_can_insert_block_intermediately_with_raw_text_only(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $b1 = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'block_order' => 1, 'raw_text' => 'Primero']);
        $b2 = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'block_order' => 2, 'raw_text' => 'Segundo']);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openNewBlockModal', 1)
            ->assertSet('showNewBlockModal', true)
            ->assertSet('insertAfterOrder', 1)
            ->set('newBlockRawText', 'Acción intermedia insertada con Gutenberg')
            ->call('createNewBlock')
            ->assertSet('showNewBlockModal', false);

        $this->assertEquals(3, $scene->scriptBlocks()->count());
        $this->assertEquals(1, $b1->fresh()->block_order);
        $this->assertEquals(3, $b2->fresh()->block_order);

        $inserted = $scene->scriptBlocks()->where('block_order', 2)->first();
        $this->assertNotNull($inserted);
        $this->assertEquals('Acción intermedia insertada con Gutenberg', $inserted->raw_text);
        $this->assertEquals('raw', $inserted->stage);
    }

    public function test_drawer_opens_for_block_and_allows_pipeline_stage_navigation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Idea inicial en bruto',
            'structured_text' => 'Tratamiento inicial',
        ]);

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id, 'raw')
            ->assertSet('detailBlockId', $block->id)
            ->assertSet('detailActiveTab', 'raw')
            ->call('setDetailTab', 'structured')
            ->assertSet('detailActiveTab', 'structured')
            ->call('startEditingInDrawer')
            ->assertSet('isEditingInDrawer', true)
            ->set('editStructuredText', 'Tratamiento modificado desde el drawer al 60%')
            ->call('saveBlockFromDrawer')
            ->assertSet('isEditingInDrawer', false)
            ->call('setPipelineStage', 'screenplay')
            ->assertSet('pipelineStage', 'screenplay')
            ->assertDispatched('scroll-to-selected-block');

        $this->assertEquals('Tratamiento modificado desde el drawer al 60%', $block->fresh()->structured_text);
    }

    public function test_reorder_block_moves_positions_atomically(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $b1 = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'block_order' => 1, 'raw_text' => 'Bloque 1']);
        $b2 = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'block_order' => 2, 'raw_text' => 'Bloque 2']);
        $b3 = ScriptBlock::factory()->create(['scene_id' => $scene->id, 'block_order' => 3, 'raw_text' => 'Bloque 3']);

        // Drag Block 1 and drop at position 3
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('activeSceneId', $scene->id)
            ->call('reorderBlock', $b1->id, 3)
            ->assertSet('selectedBlockId', $b1->id);

        $this->assertEquals(1, $b2->fresh()->block_order);
        $this->assertEquals(2, $b3->fresh()->block_order);
        $this->assertEquals(3, $b1->fresh()->block_order);

        // Drag Block 1 back to position 1
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('activeSceneId', $scene->id)
            ->call('reorderBlock', $b1->id, 1);

        $this->assertEquals(1, $b1->fresh()->block_order);
        $this->assertEquals(2, $b2->fresh()->block_order);
        $this->assertEquals(3, $b3->fresh()->block_order);
    }

    public function test_manual_editing_locks_stage_and_blocks_accidental_regeneration_until_unlocked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Texto bruto inicial.',
            'structured_text' => 'Tratamiento generado por IA.',
            'stage' => 'structured',
            'user_locks' => [],
        ]);

        $this->assertFalse($block->isStageLocked('structured'));

        // Editar manualmente desde el drawer
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id, 'structured')
            ->call('startEditingInDrawer')
            ->set('editStructuredText', 'Tratamiento con cambios manuales del autor.')
            ->call('saveBlockFromDrawer');

        $freshBlock = $block->fresh();
        $this->assertTrue($freshBlock->isStageLocked('structured'));
        $this->assertTrue($freshBlock->hasManualOverride('structured'));

        // Intentar regenerar desde el paso anterior debe estar bloqueado y conservar el texto
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('structureBlock', $freshBlock->id)
            ->assertSee('protegido');

        $this->assertEquals('Tratamiento con cambios manuales del autor.', $freshBlock->fresh()->structured_text);

        // Desbloquear manualmente la etapa
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('toggleStageLock', $freshBlock->id, 'structured')
            ->assertSee('desbloqueada');

        $this->assertFalse($freshBlock->fresh()->isStageLocked('structured'));
    }

    public function test_screenplay_manual_edit_blocks_format_to_screenplay_until_unlocked(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'structured_text' => 'Pedro camina.',
            'screenplay_content' => "PEDRO\nHola a todos.",
            'stage' => 'screenplay',
            'user_locks' => [],
        ]);

        // Guardar edición manual
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id, 'screenplay')
            ->call('startEditingInDrawer')
            ->set('editScreenplayContent', "PEDRO\nModificación manual de diálogo.")
            ->call('saveBlockFromDrawer');

        $freshBlock = $block->fresh();
        $this->assertTrue($freshBlock->isStageLocked('screenplay'));

        // Intentar regenerar guión desde etapa anterior debe estar bloqueado y conservar el texto
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('formatBlockToScreenplay', $freshBlock->id)
            ->assertSee('protegido');

        $this->assertEquals("PEDRO\nModificación manual de diálogo.", $freshBlock->fresh()->screenplay_content);

        // Desbloquear manualmente
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('toggleStageLock', $freshBlock->id, 'screenplay')
            ->assertSee('desbloqueada');

        $this->assertFalse($freshBlock->fresh()->isStageLocked('screenplay'));
    }

    public function test_locking_screenplay_also_locks_structured_and_prevents_regeneration_from_raw(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Texto bruto original.',
            'structured_text' => 'Tratamiento inicial.',
            'screenplay_content' => "JUAN\nTexto de guión.",
            'stage' => 'screenplay',
            'user_locks' => [],
        ]);

        // Bloquear guión manualmente
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('toggleStageLock', $block->id, 'screenplay');

        $freshBlock = $block->fresh();
        $this->assertTrue($freshBlock->isStageLocked('screenplay'));
        // Tratamiento también debe estar bloqueado automáticamente para no regenerar desde el bruto
        $this->assertTrue($freshBlock->isStageLocked('structured'));

        // Intentar regenerar tratamiento desde el bruto debe estar bloqueado
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('structureBlock', $freshBlock->id)
            ->assertSee('protegido');

        $this->assertEquals('Tratamiento inicial.', $freshBlock->fresh()->structured_text);
    }

    public function test_top_navigation_menu_switches_between_pipeline_and_consolidated_story_architect_views(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'PROYECTO CINE TEST']);
        $scene1 = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. CAFETERIA - DIA',
        ]);
        $scene2 = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 2,
            'slugline' => 'EXT. CALLE - NOCHE',
        ]);

        $b1 = ScriptBlock::factory()->create([
            'scene_id' => $scene1->id,
            'block_order' => 1,
            'raw_text' => 'Idea bruta bloque 1.',
            'structured_text' => 'Tratamiento narrativo bloque 1.',
            'screenplay_content' => "CARLOS\nHola mundo.",
        ]);

        $b2 = ScriptBlock::factory()->create([
            'scene_id' => $scene2->id,
            'block_order' => 1,
            'raw_text' => 'Idea bruta escena 2.',
            'structured_text' => 'Tratamiento escena 2.',
            'screenplay_content' => "ANA\nBuenas noches.",
        ]);

        $test = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSet('activeView', 'pipeline')
            ->assertSee('Pipeline')
            ->assertSee('Idea')
            ->assertSee('Tratamiento')
            ->assertSee('Guion')
            ->assertSee('Planos/Tomas')
            ->assertDontSee('Montaje');

        // 1. Cambiar a vista 'idea'
        $test->call('setActiveView', 'idea')
            ->assertSet('activeView', 'idea')
            ->assertSee('Consolidado Story Architect · Idea / Texto Bruto')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('Idea bruta bloque 1.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('Idea bruta escena 2.');

        // 2. Cambiar a vista 'structured' (Tratamiento)
        $test->call('setActiveView', 'structured')
            ->assertSet('activeView', 'structured')
            ->assertSee('Consolidado Story Architect · Tratamiento Narrativo')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('Tratamiento narrativo bloque 1.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('Tratamiento escena 2.');

        // 3. Cambiar a vista 'screenplay' (Guion)
        $test->call('setActiveView', 'screenplay')
            ->assertSet('activeView', 'screenplay')
            ->assertSee('Consolidado Story Architect · Guion Literario')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('CARLOS')
            ->assertSee('Hola mundo.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('ANA')
            ->assertSee('Buenas noches.');

        // 4. Cambiar a vista 'shots' (Planos/Tomas)
        $test->call('setActiveView', 'shots')
            ->assertSet('activeView', 'shots')
            ->assertSee('Consolidado Story Architect · Planos / Tomas Técnicas')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('2. EXT. CALLE - NOCHE');

        // 5. Volver a 'pipeline'
        $test->call('setActiveView', 'pipeline')
            ->assertSet('activeView', 'pipeline');
    }
}
