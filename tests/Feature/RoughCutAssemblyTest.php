<?php

namespace Tests\Feature;

use App\Livewire\Studio\RoughCutTimeline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\RoughCutAssemblyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RoughCutAssemblyTest extends TestCase
{
    use RefreshDatabase;

    public function test_assembly_service_produces_rendered_video_file_from_good_takes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take1.mp4",
            'technical_meta' => ['duration_seconds' => 6.2],
        ]);
        Storage::disk('public')->put($take->file_path, 'binary-take-data');

        $service = app(RoughCutAssemblyService::class);
        $result = $service->assemble($project);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['render_path']);
        $this->assertSame(1, $result['total_takes']);
        $this->assertEquals(6.2, $result['total_duration_seconds']);
        Storage::disk('public')->assertExists($result['render_path']);
    }

    public function test_assembly_service_throws_exception_when_no_good_takes_exist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        Take::factory()->create([
            'shot_id' => $shot->id,
            'is_good_take' => false,
        ]);

        $service = app(RoughCutAssemblyService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay tomas aprobadas');

        $service->assemble($project);
    }

    public function test_livewire_rough_cut_timeline_triggers_assembly_and_exposes_video_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take1.mp4",
        ]);
        Storage::disk('public')->put($take->file_path, 'binary-take-data');

        $component = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project])
            ->call('assembleRoughCut');

        $renderedUrl = $component->get('renderedVideoUrl');
        $this->assertNotNull($renderedUrl);
        $component->assertSee('Pre-corte ensamblado con éxito');
    }
}
