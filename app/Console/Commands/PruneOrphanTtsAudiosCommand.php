<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ScriptBlock;
use App\Services\TextToSpeechService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PruneOrphanTtsAudiosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tts:prune-orphans
                            {--days=7 : Días mínimos de antigüedad desde la última modificación para eliminar}
                            {--dry-run : Simula la limpieza sin eliminar físicamente ningún archivo}
                            {--force : Omite preguntas interactivas de confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta y purga archivos de audio WAV huérfanos en tts_cache sin referencia en ningún guion activo';

    /**
     * Execute the console command.
     */
    public function handle(TextToSpeechService $tts): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $cacheDir = storage_path('tts_cache');
        if (! File::isDirectory($cacheDir)) {
            $this->info("El directorio de caché TTS [{$cacheDir}] no existe. Nada que limpiar.");

            return Command::SUCCESS;
        }

        $files = File::files($cacheDir);
        $totalFiles = count($files);

        if ($totalFiles === 0) {
            $this->info('El directorio tts_cache está completamente vacío.');

            return Command::SUCCESS;
        }

        $this->info("Iniciando auditoría de {$totalFiles} archivos de audio en tts_cache...");

        // 1. Recopilar todos los hashes activos de todos los proyectos en la BD
        $activeHashes = $this->collectActiveHashes($tts);
        $this->info('Hashes activos identificados en la base de datos: '.count($activeHashes));

        $cutoffDate = now()->subDays($days);
        $orphansToDelete = [];
        $totalOrphanBytes = 0;
        $activeCount = 0;
        $recentUnreferencedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'wav') {
                continue;
            }

            $hash = $file->getFilenameWithoutExtension();
            if (isset($activeHashes[$hash])) {
                $activeCount++;

                continue;
            }

            // Es un archivo sin referencia en ningún guion activo
            $lastModified = Carbon::createFromTimestamp($file->getMTime());
            if ($lastModified->greaterThan($cutoffDate)) {
                // Modificado recientemente: se conserva por seguridad preventiva (ej. tests recientes o pruebas)
                $recentUnreferencedCount++;

                continue;
            }

            $orphansToDelete[] = $file;
            $totalOrphanBytes += $file->getSize();
        }

        $orphanCount = count($orphansToDelete);
        $mbFreed = round($totalOrphanBytes / (1024 * 1024), 2);

        $this->table(
            ['Archivos Totales', 'Activos en Uso', 'Huérfanos Recientes (< '.$days.'d)', 'Huérfanos a Purgar', 'Espacio a Liberar'],
            [[$totalFiles, $activeCount, $recentUnreferencedCount, $orphanCount, "{$mbFreed} MB"]]
        );

        if ($orphanCount === 0) {
            $this->info('No se encontraron audios huérfanos candidatos a eliminación.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Modo simulación: se habrían eliminado {$orphanCount} archivos ({$mbFreed} MB).");

            return Command::SUCCESS;
        }

        if (! $force && ! $this->confirm("¿Deseas eliminar definitivamente {$orphanCount} archivos huérfanos ({$mbFreed} MB)?")) {
            $this->comment('Operación de purga cancelada por el usuario.');

            return Command::SUCCESS;
        }

        $deletedCount = 0;
        foreach ($orphansToDelete as $orphanFile) {
            if (File::delete($orphanFile->getRealPath())) {
                $deletedCount++;
            }
        }

        $this->info("✅ Purga completada: se han eliminado {$deletedCount} archivos huérfanos ({$mbFreed} MB liberados).");

        return Command::SUCCESS;
    }

    /**
     * Construye un mapa hash de todos los segmentos activos en todos los proyectos de la base de datos.
     *
     * @return array<string, bool>
     */
    protected function collectActiveHashes(TextToSpeechService $tts): array
    {
        $activeHashes = [];
        $speeds = [1.0, 1.25, 1.5];

        $projects = Project::with('characters')->get();

        foreach ($projects as $project) {
            $characters = $project->characters;
            $blocks = ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $project->id))->get();

            foreach ($blocks as $block) {
                $segments = $tts->parseBlockToSegments($block, $characters, $project);
                foreach ($segments as $segment) {
                    $text = trim($segment['text'] ?? '');
                    if ($text === '') {
                        continue;
                    }

                    $voice = $segment['voice'] ?? null;
                    foreach ($speeds as $speed) {
                        $path = $tts->getCachedAudioPath($text, $voice, $speed);
                        $hash = pathinfo($path, PATHINFO_FILENAME);
                        $activeHashes[$hash] = true;
                    }
                }
            }
        }

        return $activeHashes;
    }
}
