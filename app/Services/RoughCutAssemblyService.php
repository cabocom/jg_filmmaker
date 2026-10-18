<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Take;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RoughCutAssemblyService
{
    /**
     * Ensambla una secuencia continua de vídeo a partir de las tomas aprobadas.
     *
     * @return array{success: bool, render_path: string, video_url: string, total_takes: int, total_duration_seconds: float, is_simulated: bool}
     *
     * @throws RuntimeException
     */
    public function assemble(Project $project, ?string $sceneId = null): array
    {
        $goodTakes = $this->getApprovedTakes($project, $sceneId);

        if ($goodTakes->isEmpty()) {
            throw new RuntimeException('No hay tomas aprobadas (is_good_take = true) disponibles para ensamblar el pre-corte.');
        }

        $renderUuid = (string) Str::uuid();
        $relativeRenderPath = "media/projects/{$project->id}/renders/rough_cut_{$renderUuid}.mp4";
        $totalDuration = (float) $goodTakes->sum(function (Take $take) {
            return (float) ($take->technical_meta['duration_seconds'] ?? 5.0);
        });

        // Asegurar que el directorio de salida existe
        $disk = Storage::disk('public');
        $rendersDir = "media/projects/{$project->id}/renders";
        if (! $disk->exists($rendersDir)) {
            $disk->makeDirectory($rendersDir);
        }

        $isTestingOrMock = app()->environment('testing')
            || (bool) config('services.filmmaker.mock_ffmpeg', false);

        // Obtener archivos físicos válidos acotados a la ruta del proyecto
        $validFilePaths = [];
        $allowedPrefix = "media/projects/{$project->id}/";
        foreach ($goodTakes as $take) {
            if ($take->file_path && str_starts_with($take->file_path, $allowedPrefix) && ! str_contains($take->file_path, '..')) {
                if ($disk->exists($take->file_path)) {
                    $validFilePaths[] = $disk->path($take->file_path);
                }
            }
        }

        if ($isTestingOrMock) {
            // Driver de simulación determinista para pruebas automáticas o mock explícito
            $disk->put($relativeRenderPath, "SIMULATED_ROUGH_CUT_VIDEO_DATA_FOR_PROJECT_{$project->id}");
            $this->pruneOldRenders($project, 5);

            return [
                'success' => true,
                'render_path' => $relativeRenderPath,
                'video_url' => $disk->url($relativeRenderPath),
                'total_takes' => $goodTakes->count(),
                'total_duration_seconds' => round($totalDuration, 2),
                'is_simulated' => true,
            ];
        }

        if (empty($validFilePaths)) {
            throw new RuntimeException('No se encontraron archivos de vídeo físicos válidos en el almacenamiento para las tomas aprobadas.');
        }

        if (! $this->isFfmpegAvailable()) {
            throw new RuntimeException('El binario FFmpeg no se encuentra disponible en el servidor para ensamblar el pre-corte.');
        }

        // Ensamblado real mediante FFmpeg concat demuxer con reintento seguro
        $tempListFile = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_');
        if ($tempListFile === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de lista para FFmpeg.');
        }

        try {
            $listContent = '';
            foreach ($validFilePaths as $filePath) {
                $escaped = str_replace("'", "'\\''", $filePath);
                $listContent .= "file '{$escaped}'\n";
            }
            file_put_contents($tempListFile, $listContent);

            $outputAbsolutePath = $disk->path($relativeRenderPath);

            // Intento 1: Concatenación directa sin transcodificación (-c copy)
            $process = Process::timeout(120)->idleTimeout(30)->run([
                'ffmpeg',
                '-f', 'concat',
                '-safe', '0',
                '-i', $tempListFile,
                '-c', 'copy',
                $outputAbsolutePath,
                '-y',
            ]);

            if (! $process->successful() || ! file_exists($outputAbsolutePath) || filesize($outputAbsolutePath) === 0) {
                if (file_exists($outputAbsolutePath)) {
                    @unlink($outputAbsolutePath);
                }

                // Intento 2: Si -c copy falló por disparidad de códecs/parámetros, transcodificar a h264 estándar
                $retryProcess = Process::timeout(300)->idleTimeout(45)->run([
                    'ffmpeg',
                    '-f', 'concat',
                    '-safe', '0',
                    '-i', $tempListFile,
                    '-c:v', 'libx264',
                    '-pix_fmt', 'yuv420p',
                    '-c:a', 'aac',
                    $outputAbsolutePath,
                    '-y',
                ]);

                if (! $retryProcess->successful() || ! file_exists($outputAbsolutePath) || filesize($outputAbsolutePath) === 0) {
                    if (file_exists($outputAbsolutePath)) {
                        @unlink($outputAbsolutePath);
                    }
                    $errorDetails = trim($retryProcess->errorOutput() ?: $process->errorOutput());
                    throw new RuntimeException("Error al ensamblar el pre-corte con FFmpeg: {$errorDetails}");
                }
            }

            $this->pruneOldRenders($project, 5);

            return [
                'success' => true,
                'render_path' => $relativeRenderPath,
                'video_url' => $disk->url($relativeRenderPath),
                'total_takes' => $goodTakes->count(),
                'total_duration_seconds' => round($totalDuration, 2),
                'is_simulated' => false,
            ];
        } finally {
            if (file_exists($tempListFile)) {
                @unlink($tempListFile);
            }
        }
    }

    /**
     * Purga los renders antiguos del proyecto, conservando únicamente los más recientes.
     */
    public function pruneOldRenders(Project $project, int $keep = 5): void
    {
        $disk = Storage::disk('public');
        $rendersDir = "media/projects/{$project->id}/renders";

        if (! $disk->exists($rendersDir)) {
            return;
        }

        $files = $disk->files($rendersDir);
        if (count($files) <= $keep) {
            return;
        }

        usort($files, function ($a, $b) use ($disk) {
            return $disk->lastModified($b) <=> $disk->lastModified($a);
        });

        $filesToDelete = array_slice($files, $keep);
        foreach ($filesToDelete as $file) {
            $disk->delete($file);
        }
    }

    /**
     * Obtiene la colección de tomas aprobadas en estricto orden secuencial cinematográfico.
     *
     * @return Collection<int, Take>
     */
    public function getApprovedTakes(Project $project, ?string $sceneId = null): Collection
    {
        return Take::query()
            ->with(['shot.scene'])
            ->where('is_good_take', true)
            ->whereHas('shot.scene', function ($q) use ($project, $sceneId) {
                $q->where('project_id', $project->id);
                if ($sceneId) {
                    $q->where('id', $sceneId);
                }
            })
            ->join('shots', 'takes.shot_id', '=', 'shots.id')
            ->join('scenes', 'shots.scene_id', '=', 'scenes.id')
            ->orderBy('scenes.scene_order')
            ->orderBy('shots.shot_number')
            ->orderBy('takes.take_number')
            ->select('takes.*')
            ->get();
    }

    /**
     * Comprueba si el binario de FFmpeg está disponible en el entorno del sistema.
     */
    public function isFfmpegAvailable(): bool
    {
        $check = Process::run(['ffmpeg', '-version']);

        return $check->successful() && str_contains($check->output(), 'ffmpeg version');
    }
}
