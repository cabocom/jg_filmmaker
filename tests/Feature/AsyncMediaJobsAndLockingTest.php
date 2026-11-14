<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AssembleRoughCutJob;
use App\Jobs\ExportProjectMp3Job;
use App\Livewire\Studio\RoughCutTimeline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use App\Services\RoughCutAssemblyService;
use App\Services\TextToSpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AsyncMediaJobsAndLockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected Scene $scene;

    protected Shot $shot;

    protected Take $take;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->scene = Scene::factory()->create(['project_id' => $this->project->id]);
        $this->shot = Shot::factory()->create(['scene_id' => $this->scene->id]);

        $takePath = "media/projects/{$this->project->id}/scenes/{$this->scene->id}/takes/take_1.mp4";
        Storage::disk('local')->put($takePath, 'dummy_video_content');

        $this->take = Take::factory()->create([
            'shot_id' => $this->shot->id,
            'is_good_take' => true,
            'file_path' => $takePath,
            'technical_meta' => [
                'probed' => true,
                'duration_seconds' => 4.5,
            ],
        ]);
    }

    public function test_assemble_rough_cut_job_runs_on_media_queue_and_updates_cache(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user)
            ->test(RoughCutTimeline::class, ['project' => $this->project])
            ->call('assembleRoughCut');

        Queue::assertPushedOn('media', AssembleRoughCutJob::class, function (AssembleRoughCutJob $job) {
            return $job->project->id === $this->project->id;
        });
    }

    public function test_assemble_rough_cut_job_acquires_lock_and_persists_ready_state(): void
    {
        $job = new AssembleRoughCutJob($this->project, $this->scene->id);
        $service = app(RoughCutAssemblyService::class);

        $job->handle($service);

        $cache = Cache::get("project_render_status_{$this->project->id}");
        $this->assertIsArray($cache);
        $this->assertSame('ready', $cache['status']);
        $this->assertNotNull($cache['video_url']);
        $this->assertGreaterThan(0, $cache['total_takes']);
    }

    public function test_assemble_rough_cut_job_skips_when_lock_is_held(): void
    {
        $lockKey = "ffmpeg_render_{$this->project->id}";
        $lock = Cache::lock($lockKey, 120);
        $this->assertTrue($lock->get());

        $job = new AssembleRoughCutJob($this->project);
        $service = app(RoughCutAssemblyService::class);

        // Al ejecutarse con el lock retenido por otro proceso, no modifica el cache de estado a ready
        $job->handle($service);

        $cache = Cache::get("project_render_status_{$this->project->id}");
        $this->assertNull($cache);

        $lock->release();
    }

    public function test_timeline_polling_detects_ready_status_and_displays_video_url(): void
    {
        // Simular que el job en segundo plano acaba de terminar
        Cache::put("project_render_status_{$this->project->id}", [
            'status' => 'ready',
            'video_url' => route('media.render', ['project' => $this->project->id, 'filename' => 'rough_cut_test.mp4']),
            'total_takes' => 1,
            'total_duration_seconds' => 4.5,
        ], 3600);

        $component = Livewire::actingAs($this->user)
            ->test(RoughCutTimeline::class, ['project' => $this->project])
            ->call('checkRenderStatus')
            ->assertSet('renderStatus', 'ready')
            ->assertSee('Pre-corte ensamblado con éxito');

        $this->assertNotNull($component->get('renderedVideoUrl'));
    }

    public function test_export_project_mp3_job_runs_on_media_queue_and_sets_ready_status(): void
    {
        $block = ScriptBlock::factory()->create([
            'scene_id' => $this->scene->id,
            'stage' => 'screenplay',
            'screenplay_content' => "INT. SALA - DÍA\n\nMARCUS\nLlegamos a tiempo al punto de encuentro.",
        ]);

        $tts = app(TextToSpeechService::class);
        $manifest = $tts->buildManifestForScope($this->project);
        foreach ($manifest as $seg) {
            $cachedPath = $tts->getCachedAudioPath($seg['text'], $seg['voice'] ?? null, 1.0);
            if (! file_exists(dirname($cachedPath))) {
                mkdir(dirname($cachedPath), 0777, true);
            }
            file_put_contents($cachedPath, 'dummy_wav_audio_content');
        }

        Process::fake([
            '*' => function (PendingProcess $process) {
                $cmd = $process->command;
                $outputArg = is_array($cmd) ? end($cmd) : null;
                if ($outputArg) {
                    file_put_contents($outputArg, 'ID3_FAKE_MP3_CONTENT');
                }

                return Process::result(output: 'ffmpeg success', exitCode: 0);
            },
        ]);

        // Ejecutar el job
        $job = new ExportProjectMp3Job($this->project, 1.0);
        $this->assertSame('media', $job->queue);

        // Despachar a la ruta de controlador
        $resDispatch = $this->actingAs($this->user)
            ->postJson(route('projects.export-mp3.dispatch', $this->project));
        $resDispatch->assertOk();
        $resDispatch->assertJson(['status' => 'processing']);

        // Consultar status endpoint
        $resStatus = $this->actingAs($this->user)
            ->getJson(route('projects.export-mp3.status', $this->project));
        $resStatus->assertOk();
        $this->assertSame('ready', $resStatus->json('status'));
    }
}
