<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportProjectMp3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Límite de tiempo en segundos para la concatenación de audio y compresión MP3.
     */
    public int $timeout = 420;

    /**
     * Número máximo de intentos.
     */
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public float $speed = 1.0
    ) {
        $this->onQueue('media');
    }

    /**
     * Procesa la concatenación y compresión de audio a MP3 en cola desacoplada.
     */
    public function handle(TextToSpeechService $tts): void
    {
        $cacheKey = "project_mp3_status_{$this->project->id}";
        $lockKey = "ffmpeg_mp3_{$this->project->id}";
        $lock = Cache::lock($lockKey, 360);

        if (! $lock->get()) {
            Log::warning("ExportProjectMp3Job omitido: ya existe una tarea activa para el proyecto {$this->project->id}");

            return;
        }

        try {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'speed' => $this->speed,
                'started_at' => now()->timestamp,
            ], 600);

            Log::info("Iniciando ExportProjectMp3Job para proyecto {$this->project->id}");

            $tempMp3Path = $tts->exportProjectToMp3($this->project, $this->speed);

            // Persistir copia en el disco local privado del proyecto
            $relativeDest = "media/projects/{$this->project->id}/audio/screenplay_locucion.mp3";
            $disk = Storage::disk('local');
            $dir = dirname($relativeDest);
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            if (File::exists($tempMp3Path)) {
                $disk->put($relativeDest, File::get($tempMp3Path));
                @unlink($tempMp3Path);
            }

            Cache::put($cacheKey, [
                'status' => 'ready',
                'file_path' => $relativeDest,
                'completed_at' => now()->timestamp,
            ], 7200);

            Log::info("ExportProjectMp3Job finalizado con éxito para proyecto {$this->project->id}");
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now()->timestamp,
            ], 3600);

            Log::error("ExportProjectMp3Job falló para proyecto {$this->project->id}: {$e->getMessage()}");

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
