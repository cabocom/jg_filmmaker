<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Services\RoughCutAssemblyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssembleRoughCutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Límite de tiempo en segundos para el ensamblaje multimedia con FFmpeg.
     */
    public int $timeout = 420;

    /**
     * Número máximo de intentos.
     */
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public ?string $sceneId = null
    ) {
        $this->onQueue('media');
    }

    /**
     * Procesa el ensamblaje de vídeo en cola desacoplada con exclusión mutua.
     */
    public function handle(RoughCutAssemblyService $assemblyService): void
    {
        $cacheKey = "project_render_status_{$this->project->id}";
        $lockKey = "ffmpeg_render_{$this->project->id}";
        $lock = Cache::lock($lockKey, 360);

        if (! $lock->get()) {
            Log::warning("AssembleRoughCutJob omitido: ya existe un proceso de renderizado activo para el proyecto {$this->project->id}");

            return;
        }

        try {
            Cache::put($cacheKey, [
                'status' => 'rendering',
                'scene_id' => $this->sceneId,
                'started_at' => now()->timestamp,
            ], 600);

            Log::info("Iniciando AssembleRoughCutJob para proyecto {$this->project->id}");

            $result = $assemblyService->assemble($this->project, $this->sceneId);

            Cache::put($cacheKey, [
                'status' => 'ready',
                'video_url' => $result['video_url'],
                'render_path' => $result['render_path'],
                'total_takes' => $result['total_takes'],
                'total_duration_seconds' => $result['total_duration_seconds'],
                'is_simulated' => $result['is_simulated'] ?? false,
                'completed_at' => now()->timestamp,
            ], 3600);

            Log::info("AssembleRoughCutJob finalizado con éxito para proyecto {$this->project->id}");
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now()->timestamp,
            ], 3600);

            Log::error("AssembleRoughCutJob falló para proyecto {$this->project->id}: {$e->getMessage()}");

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
