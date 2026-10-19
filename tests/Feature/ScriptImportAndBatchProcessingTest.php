<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectList;
use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\ScriptImportService;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ScriptImportAndBatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.jg_agente.mock' => true,
            'services.jg_agente.secret' => 'test-secret',
        ]);

        $this->user = User::factory()->create([
            'paid_project_slots' => 10,
            'daily_token_limit' => 500000,
        ]);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'El Proyecto Original',
            'synopsis' => 'Una sinopsis inicial de prueba.',
            'status' => 'draft',
        ]);
    }

    /**
     * Test 1: Modificar nombre y detalles del proyecto desde ProjectList.
     */
    public function test_user_can_edit_project_details_from_project_list(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ProjectList::class)
            ->call('openEditModal', $this->project->id)
            ->assertSet('editingProjectId', $this->project->id)
            ->assertSet('editTitle', 'El Proyecto Original')
            ->assertSet('showEditModal', true)
            ->set('editTitle', 'El Gran Laberinto')
            ->set('editSynopsis', 'Nueva sinopsis de la obra cinematográfica.')
            ->set('editStatus', 'in_progress')
            ->call('updateProject')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'title' => 'El Gran Laberinto',
            'synopsis' => 'Nueva sinopsis de la obra cinematográfica.',
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test 2: Modificar nombre del proyecto desde la cabecera de ScriptEditor.
     */
    public function test_user_can_edit_project_title_from_script_editor(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ScriptEditor::class, ['project' => $this->project])
            ->call('openEditTitleModal')
            ->assertSet('showEditTitleModal', true)
            ->assertSet('projectTitle', 'El Proyecto Original')
            ->set('projectTitle', 'Sombras del Pasado')
            ->set('projectSynopsis', 'Una historia de misterio y redención.')
            ->call('updateProjectTitle')
            ->assertHasNoErrors()
            ->assertSet('showEditTitleModal', false);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'title' => 'Sombras del Pasado',
            'synopsis' => 'Una historia de misterio y redención.',
        ]);
    }

    /**
     * Test 3: ScriptImportService analiza correctamente guión en texto con escenas, personajes y localizaciones.
     */
    public function test_script_import_service_parses_screenplay_text(): void
    {
        $sampleScript = <<<'FOUNTAIN'
INT. BAR CENTRAL - NOCHE 1

El bar está tenuemente iluminado. La lluvia golpea el ventanal.

JUAN
No deberías haber venido aquí esta noche.

MARÍA
(sonriendo con amargura)
No tenía otra opción, Juan.

EXT. CALLEJÓN OSCURO - NOCHE 2

Juan camina apresurado bajo la lluvia torrencial. Las farolas titilan.

JUAN
¡Espera!
FOUNTAIN;

        $service = app(ScriptImportService::class);
        $result = $service->parseScreenplay($sampleScript);

        $this->assertEquals(2, $result['scenes_count']);
        $this->assertContains('JUAN', $result['characters']);
        $this->assertContains('MARÍA', $result['characters']);
        $this->assertContains('BAR CENTRAL', $result['locations']);
        $this->assertContains('CALLEJÓN OSCURO', $result['locations']);
        $this->assertCount(2, $result['scenes']);
        $this->assertNotEmpty($result['scenes'][0]['blocks']);
    }

    /**
     * Test 4: Importación destructiva en ScriptEditor reemplaza el contenido del proyecto.
     */
    public function test_user_can_import_script_destructively_into_existing_project(): void
    {
        $this->actingAs($this->user);

        // Crear escena y bloque previo
        $oldScene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'slugline' => 'INT. ESCENA ANTIGUA - DÍA 1',
        ]);
        ScriptBlock::factory()->create([
            'scene_id' => $oldScene->id,
            'raw_text' => 'Texto antiguo que debe ser eliminado.',
        ]);

        $this->assertEquals(1, $this->project->scenes()->count());

        $scriptContent = <<<'TXT'
INT. OFICINA DETECTIVE - DÍA 1

Humo de cigarrillo flota en el aire.

ROBERTO
El caso está cerrado.

EXT. MUELLE - NOCHE 2

El agua golpea suavemente los pilotes de madera.
TXT;

        $file = UploadedFile::fake()->createWithContent('guion_prueba.txt', $scriptContent);

        Livewire::test(ScriptEditor::class, ['project' => $this->project])
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->assertSet('importStep', 'upload')
            ->set('importFile', $file)
            ->call('processImportFile', app(ScriptImportService::class))
            ->assertSet('importStep', 'preview')
            // Sin marcar confirmación debe dar error
            ->set('confirmDestructiveImport', false)
            ->call('confirmAndExecuteDestructiveImport', app(ScriptImportService::class))
            ->assertHasErrors('confirmDestructiveImport')
            // Con confirmación explícita
            ->set('confirmDestructiveImport', true)
            ->call('confirmAndExecuteDestructiveImport', app(ScriptImportService::class))
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        // Verificar que la escena antigua desapareció y las nuevas están presentes
        $this->assertDatabaseMissing('scenes', ['id' => $oldScene->id]);
        $this->assertDatabaseHas('scenes', [
            'project_id' => $this->project->id,
            'slugline' => 'INT. OFICINA DETECTIVE - DÍA 1',
        ]);
        $this->assertDatabaseHas('scenes', [
            'project_id' => $this->project->id,
            'slugline' => 'EXT. MUELLE - NOCHE 2',
        ]);
        $this->assertDatabaseHas('characters', [
            'project_id' => $this->project->id,
            'name' => 'ROBERTO',
        ]);
        $this->assertDatabaseHas('locations', [
            'project_id' => $this->project->id,
            'name' => 'OFICINA DETECTIVE',
        ]);
    }

    /**
     * Test 5: Importar guión y crear nuevo proyecto desde ProjectList.
     */
    public function test_user_can_create_new_project_by_importing_script(): void
    {
        $this->actingAs($this->user);

        $scriptContent = <<<'TXT'
INT. LABORATORIO SECRETO - NOCHE 1

ALICIA
Los datos han sido confirmados.
TXT;

        $file = UploadedFile::fake()->createWithContent('proyecto_alicia.txt', $scriptContent);

        Livewire::test(ProjectList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->assertSet('importStep', 'upload')
            ->set('importFile', $file)
            ->call('processImportFile', app(ScriptImportService::class))
            ->assertSet('importStep', 'preview')
            ->set('importProjectTitle', 'Operación Alicia')
            ->call('confirmAndCreateProjectFromImport', app(ScriptImportService::class))
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'title' => 'Operación Alicia',
        ]);

        $newProject = Project::where('title', 'Operación Alicia')->first();
        $this->assertNotNull($newProject);
        $this->assertDatabaseHas('scenes', [
            'project_id' => $newProject->id,
            'slugline' => 'INT. LABORATORIO SECRETO - NOCHE 1',
        ]);
        $this->assertDatabaseHas('characters', [
            'project_id' => $newProject->id,
            'name' => 'ALICIA',
        ]);
    }

    /**
     * Test 6: Procesamiento / Renderizado en lote (Batch Processing) en ScriptEditor.
     */
    public function test_user_can_execute_batch_processing_in_script_editor(): void
    {
        $this->actingAs($this->user);

        $scene = Scene::factory()->create([
            'project_id' => $this->project->id,
            'scene_order' => 1,
            'slugline' => 'INT. SALÓN - DÍA 1',
        ]);

        $block1 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'raw_text' => 'Pedro observa el reloj con impaciencia.',
            'structured_text' => null,
            'stage' => 'raw',
            'user_locks' => ['locked_fields' => []],
        ]);

        $block2 = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 2,
            'raw_text' => 'Laura entra precipitadamente por la puerta.',
            'structured_text' => null,
            'stage' => 'raw',
            'user_locks' => ['locked_fields' => []],
        ]);

        // Bloque con candado structured (no debe ser alterado)
        $blockProtected = ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'block_order' => 3,
            'raw_text' => 'Texto bruto protegido.',
            'structured_text' => 'Tratamiento inmutable protegido por el usuario.',
            'stage' => 'structured',
            'user_locks' => ['locked_fields' => ['structured']],
        ]);

        Livewire::test(ScriptEditor::class, ['project' => $this->project])
            ->call('openBatchModal')
            ->assertSet('showBatchModal', true)
            ->set('batchScope', 'scene')
            ->set('batchStage', 'structured')
            ->call('executeBatchProcessing', app(ScriptPipelineService::class))
            ->assertHasNoErrors()
            ->assertSet('showBatchModal', false);

        // Bloques 1 y 2 deben haber sido estructurados
        $this->assertNotNull($block1->fresh()->structured_text);
        $this->assertNotNull($block2->fresh()->structured_text);

        // Bloque protegido debe conservar su texto intacto
        $this->assertEquals('Tratamiento inmutable protegido por el usuario.', $blockProtected->fresh()->structured_text);
    }
}
