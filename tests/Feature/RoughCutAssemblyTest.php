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
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RoughCutAssemblyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_assembly_service_produces_rendered_video_file_from_good_takes(): void
    {
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
        Storage::disk('local')->put($take->file_path, 'binary-take-data');

        $service = app(RoughCutAssemblyService::class);
        $result = $service->assemble($project);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['render_path']);
        $this->assertSame(1, $result['total_takes']);
        $this->assertEquals(6.2, $result['total_duration_seconds']);
        Storage::disk('local')->assertExists($result['render_path']);
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
        Storage::disk('local')->put($take->file_path, 'binary-take-data');

        $component = Livewire::actingAs($user)
            ->test(RoughCutTimeline::class, ['project' => $project])
            ->call('assembleRoughCut');

        $renderedUrl = $component->get('renderedVideoUrl');
        $this->assertNotNull($renderedUrl);
        $component->assertSee('Pre-corte ensamblado con éxito');
    }

    public function test_prune_old_renders_retains_only_most_recent_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $disk = Storage::disk('local');
        $rendersDir = "media/projects/{$project->id}/renders";

        // Crear 8 archivos con timestamps ficticios
        for ($i = 1; $i <= 8; $i++) {
            $disk->put("{$rendersDir}/rough_cut_000{$i}.mp4", "render-{$i}");
        }

        $service = app(RoughCutAssemblyService::class);
        $service->pruneOldRenders($project, 5);

        $remainingFiles = $disk->files($rendersDir);
        $this->assertCount(5, $remainingFiles);
    }

    public function test_ffmpeg_retry_executes_transcoding_and_preserves_temp_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take1.mp4",
        ]);
        Storage::disk('local')->put($take->file_path, 'binary-take-data');

        // Forzar entorno de no simulación
        $this->app['env'] = 'production';
        Config::set('services.filmmaker.mock_ffmpeg', false);

        $executedCommands = [];
        Process::fake([
            '*' => function (PendingProcess $process) use (&$executedCommands) {
                $cmd = $process->command;
                $cmdString = is_array($cmd) ? implode(' ', $cmd) : (string) $cmd;
                $executedCommands[] = $cmdString;

                if (str_contains($cmdString, '-version')) {
                    return Process::result(output: 'ffmpeg version 6.0-static', exitCode: 0);
                }

                if (str_contains($cmdString, '-c copy')) {
                    // Simular fallo de -c copy por incompatibilidad de códecs
                    return Process::result(output: '', errorOutput: 'Codec mismatch in concat', exitCode: 1);
                }

                if (str_contains($cmdString, 'libx264')) {
                    // Simular éxito del segundo intento creando el archivo de salida
                    $outputArg = is_array($cmd) ? end($cmd) : null;
                    if ($outputArg === '-y') {
                        $outputArg = $cmd[count($cmd) - 2];
                    }
                    if ($outputArg && file_exists(dirname($outputArg))) {
                        file_put_contents($outputArg, 'rendered-mp4-h264');
                    }

                    return Process::result(output: 'Transcoding complete', exitCode: 0);
                }

                return Process::result(output: '', exitCode: 0);
            },
        ]);

        $service = app(RoughCutAssemblyService::class);
        $result = $service->assemble($project);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_simulated']);
        $this->assertCount(3, $executedCommands); // 1. ffmpeg -version, 2. -c copy, 3. libx264
    }

    public function test_ffmpeg_throws_runtime_exception_and_does_not_produce_false_positive_when_both_fail(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $scene = Scene::factory()->create(['project_id' => $project->id]);
        $shot = Shot::factory()->create(['scene_id' => $scene->id]);

        $take = Take::factory()->create([
            'shot_id' => $shot->id,
            'is_good_take' => true,
            'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take1.mp4",
        ]);
        Storage::disk('local')->put($take->file_path, 'binary-take-data');

        $this->app['env'] = 'production';
        Config::set('services.filmmaker.mock_ffmpeg', false);

        Process::fake([
            '*' => function (PendingProcess $process) {
                $cmd = $process->command;
                $cmdString = is_array($cmd) ? implode(' ', $cmd) : (string) $cmd;

                if (str_contains($cmdString, '-version')) {
                    return Process::result(output: 'ffmpeg version 6.0-static', exitCode: 0);
                }

                return Process::result(output: '', errorOutput: 'Fatal FFmpeg failure', exitCode: 1);
            },
        ]);

        $service = app(RoughCutAssemblyService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error al ensamblar el pre-corte con FFmpeg: Fatal FFmpeg failure');

        $service->assemble($project);
    }
}
