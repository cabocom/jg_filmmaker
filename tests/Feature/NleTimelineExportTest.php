<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Studio\RoughCutTimeline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\Export\NleTimelineExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NleTimelineExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seconds_to_smpte_timecode_calculation(): void
    {
        $service = new NleTimelineExportService;

        $this->assertSame('00:00:00:00', $service->secondsToSmpte(0.0, 24.0));
        $this->assertSame('00:00:01:00', $service->secondsToSmpte(1.0, 24.0));
        $this->assertSame('00:00:01:12', $service->secondsToSmpte(1.5, 24.0));
        $this->assertSame('01:00:00:00', $service->secondsToSmpte(3600.0, 24.0));
        $this->assertSame('01:15:30:06', $service->secondsToSmpte(4530.25, 24.0));
    }

    public function test_cmx3600_edl_export_generates_valid_cinematic_format(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'El Secreto del Faro',
        ]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => 'INT. FARO - NOCHE',
        ]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'shot_number' => 1,
            'shot_type' => 'PG',
        ]);
        Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/takes/sc01_sh01_tk01.mp4",
            'editorial_notes' => 'Cortar antes de encender la linterna',
            'technical_meta' => ['duration_seconds' => 4.5],
        ]);

        $service = new NleTimelineExportService;
        $edl = $service->exportCmx3600Edl($project, null, 24.0);

        $this->assertStringContainsString('TITLE: EL_SECRETO_DEL_FARO', $edl);
        $this->assertStringContainsString('FCM: NON-DROP FRAME', $edl);
        $this->assertStringContainsString('001  AX       AA/V  C        00:00:00:00 00:00:04:12 01:00:00:00 01:00:04:12', $edl);
        $this->assertStringContainsString('* FROM CLIP NAME: sc01_sh01_tk01.mp4', $edl);
        $this->assertStringContainsString('* COMMENT: [ESCENA 1 - INT. FARO - NOCHE - PG] - Cortar antes de encender la linterna', $edl);
    }

    public function test_fcpxml_export_generates_valid_xml_structure_with_markers(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'title' => 'Cicatrices del Mar',
        ]);
        $scene = Scene::factory()->create([
            'project_id' => $project->id,
            'scene_order' => 2,
            'slugline' => 'EXT. PLAYA - DÍA',
        ]);
        $shot = Shot::factory()->create([
            'scene_id' => $scene->id,
            'shot_number' => 3,
            'shot_type' => 'PM',
        ]);
        Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 2,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/takes/playa_toma2.mp4",
            'editorial_notes' => 'Excelente acting',
            'technical_meta' => ['duration_seconds' => 10.0],
        ]);

        $service = new NleTimelineExportService;
        $fcpxml = $service->exportFcpxml($project, null, 24.0);

        $this->assertStringContainsString('<!DOCTYPE fcpxml>', $fcpxml);
        $this->assertStringContainsString('<fcpxml version="1.10">', $fcpxml);
        $this->assertStringContainsString('<format id="r1" name="FFVideoFormat1080p24" frameDuration="1/24s"', $fcpxml);
        $this->assertStringContainsString('name="playa_toma2.mp4"', $fcpxml);
        $this->assertStringContainsString('<marker start="0s" duration="1/24s" value="[Escena 2 - PM] Excelente acting"/>', $fcpxml);
        $this->assertStringContainsString('</spine>', $fcpxml);
    }

    public function test_livewire_rough_cut_timeline_exports_edl_and_fcpxml(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);
        Take::factory()->create([
            'shot_id' => $shot->id,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/takes/take.mp4",
        ]);

        $this->actingAs($user);

        $component = Livewire::test(RoughCutTimeline::class, ['project' => $project]);

        $edlResponse = $component->call('exportCmx3600Edl');
        $this->assertNotNull($edlResponse);

        $fcpxmlResponse = $component->call('exportFcpxml');
        $this->assertNotNull($fcpxmlResponse);
    }
}
