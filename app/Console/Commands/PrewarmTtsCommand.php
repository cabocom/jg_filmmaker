<?php

namespace App\Console\Commands;

use App\Jobs\PrewarmScopeTtsJob;
use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Console\Command;

class PrewarmTtsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tts:prewarm
                            {project : ID del proyecto cinematográfico}
                            {--scene_id= : ID opcional de la escena específica}
                            {--block_id= : ID opcional del bloque específico}
                            {--speed=1.0 : Velocidad de reproducción (0.5 a 2.0)}
                            {--sync : Ejecutar sincrónicamente en primer plano}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-sintetiza y almacena en caché persistente los segmentos de audio de un proyecto o escena';

    /**
     * Execute the console command.
     */
    public function handle(TextToSpeechService $tts): int
    {
        $projectId = (string) $this->argument('project');
        $project = Project::find($projectId);

        if (! $project) {
            $this->error("Proyecto '{$projectId}' no encontrado.");

            return Command::FAILURE;
        }

        $sceneId = $this->option('scene_id') ?: null;
        $blockId = $this->option('block_id') ?: null;
        $speed = max(0.5, min(2.0, (float) $this->option('speed')));

        $this->info("Iniciando pre-warming TTS para: {$project->title} ({$project->id})");

        if ($this->option('sync')) {
            $manifest = $tts->buildManifestForScope($project, $sceneId, $blockId);
            $this->output->progressStart(count($manifest));

            $stats = $tts->prewarmManifest($manifest, $speed, function () {
                $this->output->progressAdvance();
            });

            $this->output->progressFinish();
            $this->table(
                ['Total', 'En Caché Previa', 'Sintetizados Nuevos', 'Fallidos'],
                [[$stats['total'], $stats['cached'], $stats['synthesized'], $stats['failed']]]
            );

            return Command::SUCCESS;
        }

        // Ejecutar el Job
        PrewarmScopeTtsJob::dispatchSync($project, $sceneId, $blockId, $speed);
        $this->info('Pre-warming de locución completado exitosamente.');

        return Command::SUCCESS;
    }
}
