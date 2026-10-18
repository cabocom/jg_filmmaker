<?php

namespace Tests\Feature;

use App\Livewire\Studio\RoughCutTimeline;
use App\Livewire\Studio\ScriptEditor;
use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VideoProbingAndRenderGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_tokens_used_today_is_cached_and_invalidated_on_log_creation(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        AgentRequestLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tool_name' => 'script_analysis',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
            'created_at' => now(),
        ]);

        $this->assertSame(150, $user->getTokensUsedToday());

        // Simulamos inserción directa en BD sin disparar evento de Eloquent para verificar que el valor sigue en caché
        DB::table('agent_request_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tool_name' => 'script_analysis',
            'input_tokens' => 200,
            'output_tokens' => 100,
            'total_tokens' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Debe seguir leyendo 150 desde la caché
        $this->assertSame(150, $user->getTokensUsedToday());

        // Ahora creamos un log mediante Eloquent, lo que debe invalidar la clave de caché
        AgentRequestLog::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'tool_name' => 'script_analysis',
            'input_tokens' => 50,
            'output_tokens' => 50,
            'total_tokens' => 100,
            'created_at' => now(),
        ]);

        // La caché ha sido invalidada; el nuevo total es 150 + 300 + 100 = 550
        $this->assertSame(550, $user->getTokensUsedToday());
    }

    public function test_rough_cut_timeline_memoizes_good_takes_query(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);
        Take::factory()->create(['shot_id' => $shot->id, 'take_number' => 1, 'is_good_take' => true]);

        $component = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project]);

        $instance = $component->instance();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $firstCall = $instance->getGoodTakes();
        $queryCountFirst = count(DB::getQueryLog());

        $secondCall = $instance->getGoodTakes();
        $queryCountSecond = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($queryCountFirst, $queryCountSecond, 'La segunda llamada a getGoodTakes no debe ejecutar consultas SQL adicionales');
        $this->assertSame($firstCall, $secondCall);
    }

    public function test_rough_cut_export_does_not_fabricate_metadata_when_take_not_probed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Cine Realista']);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);
        Take::factory()->create([
            'shot_id' => $shot->id,
            'take_number' => 1,
            'is_good_take' => true,
            'technical_meta' => null, // Sin sondear
        ]);

        $testable = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project])
            ->call('exportRoughCutJson');

        $testable->assertFileDownloaded();
        $downloadEffect = $testable->effects['download'];
        $data = json_decode(base64_decode($downloadEffect['content']), true);

        $this->assertIsArray($data);
        $this->assertEquals(0.0, $data['total_estimated_duration_seconds']);
        $technicalMeta = $data['timeline'][0]['technical_meta'];

        $this->assertFalse($technicalMeta['probed']);
        $this->assertNull($technicalMeta['duration_seconds']);
        $this->assertNull($technicalMeta['resolution']);
        $this->assertNull($technicalMeta['fps']);
        $this->assertNull($technicalMeta['codec']);
    }

    public function test_upload_take_video_probes_media_with_ffprobe_safely(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id, 'scene_order' => 1]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id, 'shot_number' => 1]);
        $take = Take::factory()->create(['shot_id' => $shot->id, 'take_number' => 1]);

        $mockProbeJson = json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'width' => 1920,
                    'height' => 1080,
                    'duration' => '12.450000',
                    'r_frame_rate' => '24/1',
                    'pix_fmt' => 'yuv420p',
                    'bit_rate' => '4500000',
                ],
            ],
            'format' => [
                'duration' => '12.450000',
            ],
        ]);

        Process::fake([
            '*' => Process::result(output: $mockProbeJson, exitCode: 0),
        ]);

        $file = UploadedFile::fake()->create('take_scene1.mp4', 1024, 'video/mp4');

        Livewire::actingAs($user)
            ->test(ScriptEditor::class, ['project' => $project])
            ->set('uploadingTakeId', $take->id)
            ->set('videoUpload', $file)
            ->call('uploadTakeVideo');

        $take->refresh();
        $this->assertNotNull($take->file_path);
        $this->assertTrue($take->technical_meta['probed']);
        $this->assertEquals(12.45, $take->technical_meta['duration_seconds']);
        $this->assertSame('1920x1080', $take->technical_meta['resolution']);
        $this->assertEquals(24.0, $take->technical_meta['fps']);
        $this->assertSame('h264', $take->technical_meta['codec']);
    }
}
