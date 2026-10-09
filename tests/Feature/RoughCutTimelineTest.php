<?php

namespace Tests\Feature;

use App\Livewire\Studio\RoughCutTimeline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoughCutTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_rough_cut_timeline_only_includes_good_takes_ordered_chronologically(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $scene1 = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot1 = Shot::factory()->create(['scene_id' => $scene1->id, 'shot_number' => 1]);
        $shot2 = Shot::factory()->create(['scene_id' => $scene1->id, 'shot_number' => 2]);

        // Plano 1: Toma 1 (descarte), Toma 2 (buena)
        $take1_1 = Take::factory()->create(['shot_id' => $shot1->id, 'take_number' => 1, 'is_good_take' => false]);
        $take1_2 = Take::factory()->create(['shot_id' => $shot1->id, 'take_number' => 2, 'is_good_take' => true]);

        // Plano 2: Toma 1 (buena)
        $take2_1 = Take::factory()->create(['shot_id' => $shot2->id, 'take_number' => 1, 'is_good_take' => true]);

        $component = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project]);

        $goodTakes = $component->instance()->getGoodTakes();

        $this->assertCount(2, $goodTakes);
        $this->assertFalse($goodTakes->contains('id', $take1_1->id));
        $this->assertTrue($goodTakes->contains('id', $take1_2->id));
        $this->assertTrue($goodTakes->contains('id', $take2_1->id));

        // Orden de la secuencia
        $this->assertSame($take1_2->id, $goodTakes[0]->id);
        $this->assertSame($take2_1->id, $goodTakes[1]->id);
    }

    public function test_rough_cut_timeline_filters_by_scene_when_selected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $scene1 = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $scene2 = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 2]);

        $shot1 = Shot::factory()->create(['scene_id' => $scene1->id, 'shot_number' => 1]);
        $shot2 = Shot::factory()->create(['scene_id' => $scene2->id, 'shot_number' => 1]);

        $takeScene1 = Take::factory()->create(['shot_id' => $shot1->id, 'is_good_take' => true]);
        $takeScene2 = Take::factory()->create(['shot_id' => $shot2->id, 'is_good_take' => true]);

        // Filtro activado para escena 1
        $component = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, [
                'project' => $project,
                'activeSceneId' => $scene1->id,
            ]);

        $goodTakesScene1 = $component->instance()->getGoodTakes();
        $this->assertCount(1, $goodTakesScene1);
        $this->assertSame($takeScene1->id, $goodTakesScene1[0]->id);

        // Cambiar filtro a escena 2
        $component->call('setSceneFilter', $scene2->id);
        $goodTakesScene2 = $component->instance()->getGoodTakes();
        $this->assertCount(1, $goodTakesScene2);
        $this->assertSame($takeScene2->id, $goodTakesScene2[0]->id);
    }

    public function test_rough_cut_exports_structured_json_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Vientos de Octubre']);

        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1, 'slugline' => 'EXT. PLAYA - DÍA']);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1, 'shot_type' => 'PG']);
        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
            'editorial_notes' => 'Toma limpia de apertura.',
            'technical_meta' => [
                'duration_seconds' => 7.5,
                'resolution' => '3840x2160',
                'fps' => 24.0,
                'codec' => 'prores',
            ],
        ]);

        $testable = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project])
            ->call('exportRoughCutJson');

        $testable->assertFileDownloaded();

        $downloadEffect = $testable->effects['download'];
        $this->assertSame('application/json', $downloadEffect['contentType']);
        $this->assertStringStartsWith('corte-montaje-vientos-de-octubre', $downloadEffect['name']);

        $jsonContent = base64_decode($downloadEffect['content']);
        $data = json_decode($jsonContent, true);

        $this->assertIsArray($data);
        $this->assertSame('Vientos de Octubre', $data['project']['title']);
        $this->assertSame(1, $data['total_cuts']);
        $this->assertEquals(7.5, $data['total_estimated_duration_seconds']);
        $this->assertCount(1, $data['timeline']);

        $cut = $data['timeline'][0];
        $this->assertSame(1, $cut['sequence_index']);
        $this->assertSame('PG', $cut['shot_type']);
        $this->assertSame('Toma limpia de apertura.', $cut['editorial_notes']);
        $this->assertEquals(7.5, $cut['technical_meta']['duration_seconds']);
    }
}
