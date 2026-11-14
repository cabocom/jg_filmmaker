<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NormalizeTakeMediaJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\RoughCutAssemblyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NormalizeTakeMediaJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_take_media_job_creates_mezzanine_proxy_and_poster(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $block = ScriptBlock::factory()->create(['scene_id' => $scene->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'script_block_id' => $block->id]);

        $rawFilePath = "media/projects/{$project->id}/takes/raw_take_iphone_vfr.mov";
        Storage::disk('local')->put($rawFilePath, 'RAW_IPHONE_VFR_VIDEO_BYTES');

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => $rawFilePath,
            'is_good_take' => true,
        ]);

        $this->assertNull($take->proxy_file_path);
        $this->assertNull($take->thumbnail_path);
        $this->assertSame($rawFilePath, $take->effectiveVideoPath());

        // Ejecutar Job de normalización
        NormalizeTakeMediaJob::dispatchSync($take->id);

        $take->refresh();

        $this->assertNotNull($take->proxy_file_path);
        $this->assertNotNull($take->thumbnail_path);
        $this->assertTrue(Storage::disk('local')->exists($take->proxy_file_path));
        $this->assertTrue(Storage::disk('local')->exists($take->thumbnail_path));

        // effectiveVideoPath debe preferir el proxy mezzanine
        $this->assertSame($take->proxy_file_path, $take->effectiveVideoPath());

        $this->assertIsArray($take->technical_meta);
        $this->assertTrue($take->technical_meta['is_normalized']);
        $this->assertStringContainsString('CFR 24fps', $take->technical_meta['mezzanine_profile']);
    }

    public function test_deleting_take_cleans_up_mezzanine_proxy_and_thumbnail_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $rawFilePath = "media/projects/{$project->id}/takes/take_orig.mp4";
        $proxyPath = "media/projects/{$project->id}/takes/proxies/take_proxy.mp4";
        $thumbPath = "media/projects/{$project->id}/takes/thumbnails/take_thumb.jpg";

        Storage::disk('local')->put($rawFilePath, 'ORIGINAL');
        Storage::disk('local')->put($proxyPath, 'PROXY');
        Storage::disk('local')->put($thumbPath, 'THUMB');

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => $rawFilePath,
            'proxy_file_path' => $proxyPath,
            'thumbnail_path' => $thumbPath,
        ]);

        $this->assertTrue(Storage::disk('local')->exists($proxyPath));
        $this->assertTrue(Storage::disk('local')->exists($thumbPath));

        $take->delete();

        $this->assertFalse(Storage::disk('local')->exists($rawFilePath));
        $this->assertFalse(Storage::disk('local')->exists($proxyPath));
        $this->assertFalse(Storage::disk('local')->exists($thumbPath));
    }

    public function test_rough_cut_assembly_service_uses_mezzanine_proxy_in_timeline(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);

        $rawFilePath = "media/projects/{$project->id}/takes/take_01.mp4";
        $proxyPath = "media/projects/{$project->id}/takes/proxies/take_01_mezzanine.mp4";

        Storage::disk('local')->put($rawFilePath, 'RAW_BYTES');
        Storage::disk('local')->put($proxyPath, 'PROXY_BYTES');

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'file_path' => $rawFilePath,
            'proxy_file_path' => $proxyPath,
            'is_good_take' => true,
        ]);

        $assemblyService = app(RoughCutAssemblyService::class);
        $result = $assemblyService->assemble($project);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['total_takes']);
        $this->assertTrue(Storage::disk('local')->exists($result['render_path']));
    }
}
