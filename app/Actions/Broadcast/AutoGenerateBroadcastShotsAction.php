<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Jobs\AssembleBroadcastEpisodeJob;
use App\Jobs\RenderBroadcastShotJob;
use App\Jobs\RunBroadcastStageJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\User;
use App\Services\Broadcast\OralShotSanitizer;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class AutoGenerateBroadcastShotsAction
{
    public function __construct(
        protected ScriptPipelineService $pipeline,
        protected OralShotSanitizer $sanitizer
    ) {}

    /**
     * Inicializa el flujo headless creando Project, Scene, ScriptBlock y BroadcastJob en estado RAW,
     * y despacha la siguiente etapa asíncrona hacia la cola de broadcast.
     */
    public function initialize(BroadcastJobRequestDTO $dto): BroadcastJob
    {
        $job = DB::transaction(function () use ($dto): BroadcastJob {
            $user = $this->resolveBroadcastUser();

            $project = Project::create([
                'user_id' => $user->id,
                'title' => $dto->title !== '' ? $dto->title : 'Broadcast '.now()->format('Y-m-d H:i:s'),
                'status' => 'in_progress',
                'storyboard_style' => $dto->visualMode,
            ]);

            $scene = Scene::create([
                'project_id' => $project->id,
                'scene_order' => 1,
                'slugline' => 'INT. ESTUDIO BROADCAST - DÍA',
                'time_of_day' => 'DÍA',
            ]);

            $block = ScriptBlock::create([
                'scene_id' => $scene->id,
                'block_order' => 1,
                'stage' => BlockStage::RAW->value,
                'raw_text' => $this->buildContextualizedRawText($dto),
            ]);

            return BroadcastJob::create([
                'channel_id' => $dto->channelId,
                'format' => $dto->format,
                'title' => $dto->title,
                'raw_text' => $dto->rawText,
                'language' => $dto->language,
                'sources' => $dto->sources,
                'visual_mode' => $dto->visualMode,
                'callback_url' => $dto->callbackUrl,
                'stage' => BroadcastJobStage::RAW,
                'project_id' => $project->id,
                'script_block_id' => $block->id,
                'artifacts' => array_filter([
                    'resolution_profile' => $dto->resolutionProfile,
                    'output_relative_path' => $dto->outputRelativePath,
                ]),
            ]);
        });

        RunBroadcastStageJob::dispatch($job->id, BroadcastJobStage::TREATMENT)->onQueue('broadcast');

        return $job;
    }

    /**
     * Ejecuta una etapa editorial específica garantizando idempotencia.
     */
    public function executeStage(BroadcastJob $job, BroadcastJobStage $targetStage): BroadcastJob
    {
        if ($job->stage->order() >= $targetStage->order() && $job->stage !== BroadcastJobStage::FAILED) {
            return $job;
        }

        /** @var ScriptBlock $block */
        $block = $job->scriptBlock()->firstOrFail();
        $artifacts = $job->artifacts ?? [];

        match ($targetStage) {
            BroadcastJobStage::TREATMENT => $this->processTreatmentStage($job, $block, $artifacts),
            BroadcastJobStage::SCREENPLAY => $this->processScreenplayStage($job, $block, $artifacts),
            BroadcastJobStage::SHOTS => $this->processShotsStage($job, $block, $artifacts),
            default => null,
        };

        return $job->refresh();
    }

    /**
     * Registra el fallo de una etapa con mensaje truncado.
     */
    public function markFailed(BroadcastJob $job, BroadcastJobStage $failedStage, string $reason): void
    {
        $job->update([
            'stage' => BroadcastJobStage::FAILED,
            'failed_stage' => $failedStage->value,
            'error_message' => mb_substr($reason, 0, 1000),
        ]);
    }

    /**
     * Inicia o reanuda la etapa de renderizado plano a plano y posterior ensamblado.
     */
    public function startRendering(BroadcastJob $job): BroadcastJob
    {
        /** @var ScriptBlock $block */
        $block = $job->scriptBlock()->firstOrFail();
        $shots = $block->shots()->orderBy('shot_number', 'asc')->get();

        if ($shots->isEmpty()) {
            throw new RuntimeException("No shots available to render for broadcast job {$job->id}.");
        }

        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $renderedShots = is_array($artifacts['rendered_shots'] ?? null) ? $artifacts['rendered_shots'] : [];

        $pendingShot = $shots->first(fn (Shot $shot): bool => ! isset($renderedShots[$shot->id]));

        $job->update([
            'stage' => BroadcastJobStage::RENDERING,
            'failed_stage' => null,
            'error_message' => null,
        ]);

        if ($pendingShot !== null) {
            RenderBroadcastShotJob::dispatch($job->id, (string) $pendingShot->id)->onQueue('broadcast');
        } else {
            AssembleBroadcastEpisodeJob::dispatch($job->id)->onQueue('broadcast');
        }

        return $job->refresh();
    }

    protected function processTreatmentStage(BroadcastJob $job, ScriptBlock $block, array $artifacts): void
    {
        $this->pipeline->transitionToStructured($block, syncProjectContext: false);
        $block->refresh();

        $artifacts['treatment'] = $block->structured_text;
        $job->update([
            'stage' => BroadcastJobStage::TREATMENT,
            'artifacts' => $artifacts,
        ]);

        RunBroadcastStageJob::dispatch($job->id, BroadcastJobStage::SCREENPLAY)->onQueue('broadcast');
    }

    protected function processScreenplayStage(BroadcastJob $job, ScriptBlock $block, array $artifacts): void
    {
        $this->pipeline->transitionToScreenplay($block, syncProjectContext: false);
        $block->refresh();

        $artifacts['screenplay'] = $block->screenplay_content;
        $job->update([
            'stage' => BroadcastJobStage::SCREENPLAY,
            'artifacts' => $artifacts,
        ]);

        RunBroadcastStageJob::dispatch($job->id, BroadcastJobStage::SHOTS)->onQueue('broadcast');
    }

    protected function processShotsStage(BroadcastJob $job, ScriptBlock $block, array $artifacts): void
    {
        $shots = $this->pipeline->generateShotsBreakdown($block);
        $sanitized = $this->sanitizer->sanitizeAndFormatShots($shots);
        $sanitized = $this->assignOralTextFromRawScript($sanitized, (string) $job->raw_text);

        $artifacts['shots'] = $sanitized;
        $artifacts['shots_count'] = count($sanitized);
        $job->update([
            'stage' => BroadcastJobStage::SHOTS,
            'artifacts' => $artifacts,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $shots
     * @return array<int, array<string, mixed>>
     */
    private function assignOralTextFromRawScript(array $shots, string $rawText): array
    {
        $shotCount = count($shots);
        $cleanRaw = trim($this->sanitizer->stripMarkdown($rawText));
        if ($shotCount === 0 || $cleanRaw === '') {
            return $shots;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $cleanRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [$cleanRaw];
        $units = count($sentences) >= $shotCount
            ? $sentences
            : (preg_split('/\s+/u', $cleanRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [$cleanRaw]);

        $unitCount = count($units);
        $chunks = array_fill(0, $shotCount, []);
        foreach ($units as $i => $unit) {
            $bucket = (int) floor($i * $shotCount / $unitCount);
            $chunks[$bucket][] = $unit;
        }

        foreach ($shots as $idx => $shot) {
            $rawChunk = ! empty($chunks[$idx]) ? implode(' ', $chunks[$idx]) : $cleanRaw;
            $oralText = $this->sanitizer->clampOralText($rawChunk);
            $words = count(preg_split('/\s+/u', trim($oralText), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $shots[$idx]['oral_text'] = $oralText;
            $shots[$idx]['word_count'] = $words;
            $shots[$idx]['estimated_seconds'] = $this->sanitizer->estimateSeconds($oralText);
        }

        return $shots;
    }

    /**
     * Resuelve el usuario del sistema broadcast o asigna el primer administrador disponible.
     */
    private function resolveBroadcastUser(): User
    {
        /** @var User|null $admin */
        $admin = User::query()->where('is_admin', true)->first();
        if ($admin !== null) {
            return $admin;
        }

        return User::firstOrCreate(
            ['email' => 'broadcast-system@jg-filmmaker.local'],
            [
                'name' => 'Broadcast System',
                'password' => bcrypt('broadcast_secret_'.bin2hex(random_bytes(16))),
                'is_admin' => true,
                'plan_tier' => 'pro',
            ]
        );
    }

    /**
     * Inyecta una cabecera de pauta según el formato broadcast y el idioma.
     */
    private function buildContextualizedRawText(BroadcastJobRequestDTO $dto): string
    {
        $formatKey = strtolower(trim($dto->format));
        $formatDescription = match ($formatKey) {
            'pill', 'pildora' => 'Píldora informativa ágil, directa y dinámica (formato vertical / micro-cápsula)',
            'news', 'noticia' => 'Crónica periodística televisiva con tono informativo riguroso',
            'debate' => 'Mesa de debate y contraste dialéctico con dinamismo oral',
            default => 'Reportaje audiovisual ágil',
        };

        $lang = strtolower(trim($dto->language));
        $langInstruction = $lang === 'en'
            ? 'Language: English. Natural oral phrasing for high-speed speech.'
            : 'Idioma: Español. Mantener narración natural para locución oral de broadcast.';

        $header = "[FORMATO: {$formatDescription}]\n"
            ."[PAUTA: {$langInstruction}]\n"
            ."[TÍTULO: {$dto->title}]\n\n";

        return $header.trim($dto->rawText);
    }
}
