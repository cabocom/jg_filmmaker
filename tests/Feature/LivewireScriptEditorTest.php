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
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
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
            ->assertSee('IDEA CONSOLIDADA')
            ->assertDontSee('Consolidado Story Architect')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('Idea bruta bloque 1.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('Idea bruta escena 2.');

        // 2. Cambiar a vista 'structured' (Tratamiento)
        $test->call('setActiveView', 'structured')
            ->assertSet('activeView', 'structured')
            ->assertSee('TRATAMIENTO CONSOLIDADO')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('Tratamiento narrativo bloque 1.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('Tratamiento escena 2.');

        // 3. Cambiar a vista 'screenplay' (Guion)
        $test->call('setActiveView', 'screenplay')
            ->assertSet('activeView', 'screenplay')
            ->assertSee('GUION CONSOLIDADO')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('CARLOS')
            ->assertSee('Hola mundo.')
            ->assertSee('2. EXT. CALLE - NOCHE')
            ->assertSee('ANA')
            ->assertSee('Buenas noches.');

        // 4. Cambiar a vista 'shots' (Planos/Tomas)
        $test->call('setActiveView', 'shots')
            ->assertSet('activeView', 'shots')
            ->assertSee('PLANOS/TOMAS CONSOLIDADO')
            ->assertSee('1. INT. CAFETERIA - DIA')
            ->assertSee('2. EXT. CALLE - NOCHE');

        // 5. Volver a 'pipeline'
        $test->call('setActiveView', 'pipeline')
            ->assertSet('activeView', 'pipeline');
    }

    public function test_plot_hole_detector_marks_affected_blocks_with_reason_and_persists_until_rerun(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. DESPACHO - DIA',
            'scene_order' => 1,
        ]);

        $blockClean = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Carlos entra en el despacho y saluda a María.',
            'structured_text' => 'Carlos entra en el despacho y saluda a María.',
            'screenplay_content' => null,
            'plot_hole' => null,
        ]);

        $blockWithHole = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 2,
            'raw_text' => '[AGUJERO: Carlos desaparece sin explicación antes de que María responda]',
            'structured_text' => '[AGUJERO: Carlos desaparece sin explicación antes de que María responda]',
            'screenplay_content' => null,
            'plot_hole' => null,
        ]);

        // Ejecutar análisis de agujeros de guión
        $test = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSee('Biblia')
            ->assertSee('Agujeros')
            ->call('analyzePlotHoles');

        // Verificar marcado y motivo en base de datos
        $freshClean = $blockClean->fresh();
        $freshHole = $blockWithHole->fresh();

        $this->assertNull($freshClean->plot_hole);
        $this->assertFalse($freshClean->hasPlotHole());

        $this->assertNotNull($freshHole->plot_hole);
        $this->assertTrue($freshHole->hasPlotHole());
        $this->assertStringContainsString('Carlos desaparece sin explicación', $freshHole->plot_hole);

        // El componente debe mostrar el motivo y el badge
        $test->assertSee('Agujero de Guión Detectado')
            ->assertSee('Carlos desaparece sin explicación');

        // Comprobar que en la vista consolidada también se ve el motivo
        $test->call('setActiveView', 'idea')
            ->assertSee('Carlos desaparece sin explicación');

        // PERSISTENCIA: Nuevo test/recarga sin pasar la herramienta conserva la marca
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSee('Agujero de Guión Detectado')
            ->assertSee('Carlos desaparece sin explicación');

        // Ahora corregir el texto y volver a pasar la herramienta
        $blockWithHole->update([
            'raw_text' => 'Carlos se sienta en la silla frente a María.',
            'structured_text' => 'Carlos se sienta en la silla frente a María.',
            'screenplay_content' => null,
        ]);

        $testRerun = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('analyzePlotHoles');

        $this->assertNull($blockWithHole->fresh()->plot_hole);
        $this->assertFalse($blockWithHole->fresh()->hasPlotHole());
        $testRerun->assertDontSee('Carlos desaparece sin explicación');
    }

    public function test_user_can_justify_and_dismiss_plot_hole_with_reason(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. BAR CENTRAL - NOCHE',
            'scene_order' => 1,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Javier conversa con el camarero.',
            'structured_text' => 'Javier pide una copa de vino.',
            'plot_hole' => 'Pedro aparece en el bar sin haber llegado previamente.',
        ]);

        $this->assertTrue($block->hasPlotHole());
        $this->assertFalse($block->hasJustifiedPlotHoles());

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSee('Pedro aparece en el bar sin haber llegado previamente.')
            ->call('openJustifyModal', $block->id)
            ->assertSet('showJustifyModal', true)
            ->assertSet('justifyingBlockId', $block->id)
            ->assertSet('justifyingHoleReason', 'Pedro aparece en el bar sin haber llegado previamente.')
            ->set('justificationText', 'Pedro no está en el bar, está esperando en el coche fuera.')
            ->call('savePlotHoleJustification')
            ->assertSet('showJustifyModal', false);

        $fresh = $block->fresh();
        $this->assertNull($fresh->plot_hole);
        $this->assertFalse($fresh->hasPlotHole());
        $this->assertTrue($fresh->hasJustifiedPlotHoles());

        $justified = $fresh->getJustifiedPlotHoles();
        $this->assertCount(1, $justified);
        $this->assertEquals('Pedro aparece en el bar sin haber llegado previamente.', $justified[0]['original_reason']);
        $this->assertEquals('Pedro no está en el bar, está esperando en el coche fuera.', $justified[0]['justification']);

        // Al abrir detalles del bloque, debe verse en la sección de Agujeros justificados
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('openBlockDetails', $block->id, 'structured')
            ->assertSee('Agujeros justificados')
            ->assertSee('Pedro no está en el bar, está esperando en el coche fuera.');
    }

    public function test_reanalyzing_plot_holes_does_not_remark_justified_holes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. BAR CENTRAL - NOCHE',
            'scene_order' => 1,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => '[AGUJERO: Pedro entra de repente]',
            'structured_text' => '[AGUJERO: Pedro entra de repente]',
            'plot_hole' => null,
            'user_locks' => [
                'justified_plot_holes' => [
                    [
                        'original_reason' => 'Pedro entra de repente',
                        'justification' => 'Es una alucinación del protagonista explicada en la escena siguiente',
                        'justified_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $this->assertTrue($block->hasJustifiedPlotHoles());

        // Al ejecutar el análisis, como ya está justificado, no debe marcarse
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('analyzePlotHoles');

        $this->assertNull($block->fresh()->plot_hole);
    }

    public function test_transition_to_structured_clears_outdated_manual_overrides(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. DESPACHO - DIA',
            'scene_order' => 1,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Texto bruto original sin cambios.',
            'structured_text' => 'Texto estructurado viejo.',
            'stage' => 'raw',
            'user_locks' => [
                'manual_diffs' => [
                    [
                        'stage' => 'structured',
                        'field' => 'structured_text',
                        'user_override' => 'Texto manual viejo que ya no existe',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
                'locked_fields' => ['structured_text'],
            ],
        ]);

        // Dado que el override ya no existe en el texto, hasManualOverride debe dar false
        $this->assertFalse($block->hasManualOverride('structured'));

        // Al ejecutar transición a structured con el servicio
        $pipelineService = app(ScriptPipelineService::class);
        $updatedBlock = $pipelineService->transitionToStructured($block);

        $this->assertFalse($updatedBlock->hasManualOverride('structured'));
        $locks = $updatedBlock->user_locks ?? [];
        $this->assertEmpty($locks['manual_diffs'] ?? []);
    }

    public function test_plot_hole_analysis_audits_each_consolidated_stage_and_marks_earliest_step(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. BAR CENTRAL - NOCHE',
            'scene_order' => 1,
        ]);

        // Bloque 1: Agujero que nace en el bruto
        $block1 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => '[AGUJERO: Contradicción temporal en bruto]',
            'structured_text' => '[AGUJERO: Contradicción temporal en bruto]',
            'screenplay_content' => '[AGUJERO: Contradicción temporal en bruto]',
            'plot_hole' => null,
        ]);

        // Bloque 2: Segundo agujero que nace en el bruto
        $block2 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 2,
            'raw_text' => '[AGUJERO: Personaje misterioso en bruto]',
            'structured_text' => '[AGUJERO: Personaje misterioso en bruto]',
            'screenplay_content' => null,
            'plot_hole' => null,
        ]);

        // Bloque 3: Bruto y tratamiento limpios, agujero introducido en guion
        $block3 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 3,
            'raw_text' => 'Javier pide una copa de vino.',
            'structured_text' => 'Javier bebe tranquilamente en la barra.',
            'screenplay_content' => '[AGUJERO: Inconsistencia introducida en guión]',
            'plot_hole' => null,
        ]);

        // Ejecutar análisis de agujeros de guión
        $livewire = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->call('analyzePlotHoles');

        // Bloque 1: Debe quedar marcado como origen 'raw' (Idea)
        $fresh1 = $block1->fresh();
        $this->assertNotNull($fresh1->plot_hole);
        $this->assertStringContainsString('Contradicción temporal en bruto', $fresh1->plot_hole);
        $this->assertEquals('raw', $fresh1->getPlotHoleStage());
        $this->assertEquals('Idea (Bruto)', $fresh1->getPlotHoleStageLabel());

        // Bloque 2: Debe quedar marcado como origen 'raw' (Idea)
        $fresh2 = $block2->fresh();
        $this->assertNotNull($fresh2->plot_hole);
        $this->assertStringContainsString('Personaje misterioso en bruto', $fresh2->plot_hole);
        $this->assertEquals('raw', $fresh2->getPlotHoleStage());
        $this->assertEquals('Idea (Bruto)', $fresh2->getPlotHoleStageLabel());

        // Bloque 3: Debe quedar marcado como origen 'screenplay' (Guion)
        $fresh3 = $block3->fresh();
        $this->assertNotNull($fresh3->plot_hole);
        $this->assertStringContainsString('Inconsistencia introducida en guión', $fresh3->plot_hole);
        $this->assertEquals('screenplay', $fresh3->getPlotHoleStage());
        $this->assertEquals('Guion', $fresh3->getPlotHoleStageLabel());

        // En la interfaz se visualizan los badges distintivos de cada etapa
        $livewire->assertSee('Detectado en: Idea (Bruto)')
            ->assertSee('Detectado en: Guion')
            ->assertSee('2 en Idea, 1 en Guion');
    }

    public function test_can_toggle_plot_hole_alerts_visibility_and_clear_plot_holes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. BAR CENTRAL - NOCHE',
            'scene_order' => 1,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Javier entra al bar.',
            'plot_hole' => 'Contradicción evidente en la escena',
            'user_locks' => ['plot_hole_stage' => 'raw'],
        ]);

        $livewire = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSet('showPlotHoleAlerts', true)
            ->assertSee('Contradicción evidente en la escena')
            ->assertSee('Agujero de Guión Detectado');

        // Desactivar mensajes de agujeros
        $livewire->call('togglePlotHoleAlerts')
            ->assertSet('showPlotHoleAlerts', false)
            ->assertDontSee('Agujero de Guión Detectado')
            ->assertDontSee('Contradicción evidente en la escena');

        // Volver a activar mensajes
        $livewire->call('togglePlotHoleAlerts')
            ->assertSet('showPlotHoleAlerts', true)
            ->assertSee('Agujero de Guión Detectado')
            ->assertSee('Contradicción evidente en la escena');

        // Borrar todas las marcas de agujeros
        $livewire->call('clearPlotHoles');
        $this->assertNull($block->fresh()->plot_hole);
        $this->assertFalse($block->fresh()->hasPlotHole());
        $livewire->assertDontSee('Agujero de Guión Detectado');
    }

    public function test_plot_hole_detector_discards_name_capitalization_and_typography_false_positives(): void
    {
        $agentClient = app(AgentClientService::class);

        $blocksData = [
            [
                'id' => 'block-1',
                'stage' => 'structured',
                'structured_text' => 'En el claro del bosque, Estrella Casilda encuentra la muñeca de trapo.',
                'raw_text' => 'En el claro del bosque, Estrella Casilda encuentra la muñeca de trapo.',
            ],
            [
                'id' => 'block-2',
                'stage' => 'screenplay',
                'screenplay_content' => "ESTRELLA CASILDA\n(asustada)\n¿Quién está ahí?",
                'raw_text' => 'Estrella Casilda habla asustada.',
            ],
        ];

        // Simulamos el caso real que dio el falso positivo del usuario
        $reasonFalsePositive = 'El nombre de la muñeca aparece escrito como "Estrella Casilda" en este bloque, diferente a "ESTRELLA CASILDA" usado en el bloque del bosque, creando una inconsistencia de nombre entre escenas.';

        // Invocamos auditPlotHoles forzando un mock o directamente usando la lógica de filtrado de auditPlotHoles
        // Mockeamos callTool interno o Http para simular la respuesta con el falso positivo
        $service = new class extends AgentClientService
        {
            public string $mockReason = '';

            public function callTool(string $toolName, array $payload = []): array
            {
                return [
                    'success' => true,
                    'detected_holes' => [
                        [
                            'block_id' => 'block-1',
                            'reason' => $this->mockReason,
                        ],
                    ],
                ];
            }
        };

        $service->mockReason = $reasonFalsePositive;
        $auditResult = $service->auditPlotHoles($blocksData, [['name' => 'Estrella Casilda']]);

        // Debe descartarse totalmente el falso positivo
        $this->assertCount(0, $auditResult['detected_holes']);
        $this->assertEquals(0, $auditResult['total_holes']);

        // En cambio, un agujero real debe conservarse
        $service->mockReason = 'Estrella Casilda desaparece sin explicación antes de terminar la acción.';
        $realResult = $service->auditPlotHoles($blocksData, [['name' => 'Estrella Casilda']]);
        $this->assertCount(1, $realResult['detected_holes']);
        $this->assertEquals('block-1', $realResult['detected_holes'][0]['block_id']);
    }

    public function test_pipeline_service_always_capitalizes_character_names_in_all_stages(): void
    {
        $pipelineService = app(ScriptPipelineService::class);

        $characters = [
            ['name' => 'Estrella Casilda'],
            ['name' => 'Juan Pedro'],
        ];

        // 1. Etapa structured (Tratamiento)
        $rawStructured = 'en la penumbra, estrella casilda se acerca lentamente mientras juan pedro la observa con desconcierto.';
        $capitalizedStructured = $pipelineService->capitalizeCharacterNames($rawStructured, $characters, 'structured');

        $this->assertStringContainsString('Estrella Casilda', $capitalizedStructured);
        $this->assertStringContainsString('Juan Pedro', $capitalizedStructured);
        $this->assertStringNotContainsString('estrella casilda', $capitalizedStructured);
        $this->assertStringNotContainsString('juan pedro', $capitalizedStructured);

        // 2. Etapa screenplay (Guión literario)
        $rawScreenplay = "estrella casilda camina hacia la puerta.\n\nestrella casilda\n(susurrando)\nNo hagas ruido.\n\njuan pedro se gira alarmado.";
        $capitalizedScreenplay = $pipelineService->capitalizeCharacterNames($rawScreenplay, $characters, 'screenplay');

        $this->assertStringContainsString('Estrella Casilda camina', $capitalizedScreenplay);
        $this->assertStringContainsString("ESTRELLA CASILDA\n(susurrando)", $capitalizedScreenplay);
        $this->assertStringContainsString('Juan Pedro se gira', $capitalizedScreenplay);
    }

    public function test_plot_hole_menu_has_ver_ocultar_y_borrar_compact_options(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. BAR CENTRAL - NOCHE',
            'scene_order' => 1,
        ]);

        $block = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Javier entra al bar.',
            'plot_hole' => 'Contradicción evidente en la escena',
            'user_locks' => ['plot_hole_stage' => 'raw'],
        ]);

        $livewire = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertSee('Agujeros')
            ->assertSee('Ver')
            ->assertSee('Ocultar')
            ->assertSee('Borrar');

        // Probar acción "Ocultar"
        $livewire->call('hidePlotHoleAlerts')
            ->assertSet('showPlotHoleAlerts', false)
            ->assertDontSee('Agujero de Guión Detectado');

        // Probar acción "Ver"
        $livewire->call('showPlotHoleAlerts')
            ->assertSet('showPlotHoleAlerts', true)
            ->assertSee('Agujero de Guión Detectado');

        // Probar acción "Borrar"
        $livewire->call('clearPlotHoles');
        $this->assertNull($block->fresh()->plot_hole);
        $livewire->assertDontSee('Agujero de Guión Detectado');
    }
}
