<?php

namespace Tests\Feature;

use App\Livewire\Studio\ScriptEditor;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BibleSyncPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_is_idempotent_and_does_not_mutate_bible_entities(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'slugline' => 'INT. DESPACHO - DÍA',
        ]);

        ScriptBlock::factory()->create([
            'scene_id' => $scene->id,
            'raw_text' => 'El detective busca pistas entre los papeles.',
        ]);

        $initialCharactersCount = $project->characters()->count();
        $initialLocationsCount = $project->locations()->count();

        // Montar ScriptEditor simulando petición GET / render inicial
        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->assertOk();

        // Verificar que no se realizaron escrituras automáticas en mount()
        $this->assertEquals($initialCharactersCount, $project->characters()->count());
        $this->assertEquals($initialLocationsCount, $project->locations()->count());
    }

    public function test_sync_project_locations_never_deletes_or_destroys_user_notes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Crear una localización con notas manuales del departamento de arte
        $originalLocation = Location::factory()->create([
            'project_id' => $project->id,
            'name' => 'CAFETERIA',
            'type' => 'INT',
            'art_department_notes' => 'Decorado vintage años 80, lámparas de neón verde.',
        ]);

        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'location_id' => $originalLocation->id,
            'slugline' => 'INT. CAFETERIA - NOCHE',
        ]);

        $pipeline = app(ScriptPipelineService::class);
        $pipeline->syncProjectLocations($project);

        $this->assertEquals($originalLocation->id, $scene->fresh()->location_id);

        // El usuario o el guionista cambia el slugline de la escena a un nuevo lugar
        $scene->update(['slugline' => 'EXT. CALLEJON - NOCHE']);

        // Sincronizar de nuevo
        $pipeline->syncProjectLocations($project);

        // La localización original NO debe haber sido eliminada ni renombrada
        $originalLocationFresh = Location::find($originalLocation->id);
        $this->assertNotNull($originalLocationFresh);
        $this->assertEquals('CAFETERIA', $originalLocationFresh->name);
        $this->assertEquals('Decorado vintage años 80, lámparas de neón verde.', $originalLocationFresh->art_department_notes);

        // Se debió crear la nueva localización y asociarse a la escena
        $newLocation = Location::where('project_id', $project->id)->where('name', 'CALLEJON')->first();
        $this->assertNotNull($newLocation);
        $this->assertEquals($newLocation->id, $scene->fresh()->location_id);
    }

    public function test_infer_characters_from_text_does_not_inject_arbitrary_hardcoded_names(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $pipeline = app(ScriptPipelineService::class);

        // Texto sin personajes explícitos ni Fountain ni roles
        $neutralText = 'Un coche avanza por una autopista solitaria bajo la lluvia.';
        $inferred = $pipeline->inferCharactersFromText($neutralText, [], $project);

        $this->assertEmpty($inferred);
        $this->assertEquals(0, $project->characters()->count());

        // Texto con roles dramáticos y Fountain explícitos
        $dramaticText = "El camarero limpia una copa con un trapo.\n\nDETECTIVE\n¿Dónde estabas anoche?";
        $inferredDramatic = $pipeline->inferCharactersFromText($dramaticText, [], $project);

        $names = array_column($inferredDramatic, 'name');
        $this->assertContains('Camarero', $names);
        $this->assertContains('Detective', $names);
    }
}
