<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrewarmScopeTtsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de segundos que el trabajo puede ejecutarse antes de que expire el tiempo de espera.
     */
    public int $timeout = 600;

    /**
     * Crea una nueva instancia del Job.
     */
    public function __construct(
        public Project $project,
        public ?string $sceneId = null,
        public ?string $blockId = null,
        public float $speed = 1.0
    ) {}

    /**
     * Ejecuta la síntesis anticipada de segmentos de locución en segundo plano.
     */
    public function handle(TextToSpeechService $tts): void
    {
        $lockKey = "tts_prewarm_{$this->project->id}";
        $lock = Cache::lock($lockKey, 300);

        if (! $lock->get()) {
            Log::info("Pre-warming de TTS omitido: Ya existe una tarea en curso para el proyecto {$this->project->id}");

            return;
        }

        try {
            Log::info("Iniciando pre-warming TTS en background para proyecto {$this->project->id}", [
                'scene_id' => $this->sceneId,
                'block_id' => $this->blockId,
            ]);

            $manifest = $tts->buildManifestForScope($this->project, $this->sceneId, $this->blockId);
            $stats = $tts->prewarmManifest($manifest, $this->speed, function ($index, $item, $status) {
                if ($index % 10 === 0 || $status === 'failed') {
                    Log::debug("Pre-warming TTS [{$status}] segmento #{$index}: {$item['speaker']}");
                }
            });

            Log::info("Pre-warming TTS completado para proyecto {$this->project->id}", $stats);
        } finally {
            $lock->release();
        }
    }
}
