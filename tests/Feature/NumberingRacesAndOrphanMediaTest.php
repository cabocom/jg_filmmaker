<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NumberingRacesAndOrphanMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_atomic_numbering_on_block_scene_shot_and_take_creation(): void
    {
        // ISSUE-023: Verificación de numeración atómica
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project]);

        // 1. Crear Escena con orden incremental
        $component->set('newSceneSlugline', 'INT. BANCO - DÍA')
            ->set('newSceneTimeOfDay', 'DÍA')
            ->call('createScene');

        $scene = Scene::where('project_id', $project->id)->first();
        $this->assertNotNull($scene);
        $this->assertEquals(1, $scene->scene_order);

        // 2. Crear bloques atómicos dentro de la escena
        $component->set('activeSceneId', $scene->id)
            ->set('newBlockRawText', 'El guardia vigila la puerta blindada.')
            ->call('createNewBlock');

        $this->assertEquals(2, $scene->scriptBlocks()->count()); // 1 inicial + 1 creado
        $this->assertEquals([1, 2], $scene->scriptBlocks()->orderBy('block_order')->pluck('block_order')->all());

        // 3. Crear Planos y Tomas atómicas
        $component->set('newShotType', 'CU')
            ->set('newShotDescription', 'Detalle de la cerradura')
            ->call('addShot');

        $shot = Shot::where('scene_id', $scene->id)->first();
        $this->assertNotNull($shot);
        $this->assertEquals(1, $shot->shot_number);
        $this->assertEquals(1, $shot->takes()->first()->take_number);

        // 4. Añadir una segunda toma al plano
        $component->set('addingTakeShotId', $shot->id)
            ->set('newTakeIsGood', true)
            ->set('newTakeEditorialNotes', 'Excelente foco')
            ->call('saveNewTake');

        $this->assertEquals(2, $shot->takes()->count());
        $this->assertEquals([1, 2], $shot->takes()->orderBy('take_number')->pluck('take_number')->all());
    }

    public function test_deleting_take_cleans_up_physical_media_file(): void
    {
        // ISSUE-025: Limpieza de archivo individual en Take::deleted
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $filePath = "media/projects/{$project->id}/takes/take_test_01.mp4";
        Storage::disk('local')->put($filePath, 'fake video content');
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => $filePath,
        ]);

        $take->delete();

        // Tras confirmar el borrado, el fichero no debe existir en disco
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    public function test_deleting_scene_cascades_media_file_cleanup(): void
    {
        // ISSUE-025: Limpieza en cascada de tomas al borrar escena
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $filePath1 = "media/projects/{$project->id}/takes/take_scene_01.mp4";
        $filePath2 = "media/projects/{$project->id}/takes/take_scene_02.mp4";
        Storage::disk('local')->put($filePath1, 'content 1');
        Storage::disk('local')->put($filePath2, 'content 2');

        Take::factory()->create(['shot_id' => $shot->id, 'file_path' => $filePath1]);
        Take::factory()->create(['shot_id' => $shot->id, 'file_path' => $filePath2]);

        $this->assertTrue(Storage::disk('local')->exists($filePath1));
        $this->assertTrue(Storage::disk('local')->exists($filePath2));

        $scene->delete();

        $this->assertFalse(Storage::disk('local')->exists($filePath1));
        $this->assertFalse(Storage::disk('local')->exists($filePath2));
    }

    public function test_deleting_project_purges_entire_media_directory(): void
    {
        // ISSUE-025: Limpieza completa del directorio de medios del proyecto
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $projectDir = "media/projects/{$project->id}";
        Storage::disk('local')->put("{$projectDir}/takes/clip1.mp4", 'clip 1');
        Storage::disk('local')->put("{$projectDir}/renders/rough_cut_01.mp4", 'render 1');

        $this->assertTrue(Storage::disk('local')->exists("{$projectDir}/takes/clip1.mp4"));

        $project->delete();

        $this->assertFalse(Storage::disk('local')->exists("{$projectDir}/takes/clip1.mp4"));
        $this->assertFalse(Storage::disk('local')->exists("{$projectDir}/renders/rough_cut_01.mp4"));
    }

    public function test_artisan_prune_orphans_detects_and_deletes_unregistered_media_files(): void
    {
        // ISSUE-025: Tarea de reconciliación de huérfanos
        $orphanPath = 'media/projects/orphan_project_id/takes/ghost_take.mp4';
        Storage::disk('local')->put($orphanPath, 'orphan data');
        $this->assertTrue(Storage::disk('local')->exists($orphanPath));

        Artisan::call('media:prune-orphans');

        $this->assertFalse(Storage::disk('local')->exists($orphanPath));
    }
}
