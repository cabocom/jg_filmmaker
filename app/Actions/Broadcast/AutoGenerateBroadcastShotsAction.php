<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Enums\BlockStage;
use App\Enums\BroadcastJobStage;
use App\Enums\ProjectCategory;
use App\Jobs\AssembleBroadcastEpisodeJob;
use App\Jobs\RenderBroadcastShotJob;
use App\Jobs\RunBroadcastStageJob;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\User;
use App\Services\Broadcast\OralShotSanitizer;
use App\Services\Broadcast\SentenceBasedShotDistributor;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class AutoGenerateBroadcastShotsAction
{
    public function __construct(
        protected ScriptPipelineService $pipeline,
        protected OralShotSanitizer $sanitizer,
        protected ?SyncBroadcastProjectBibleAction $bibleSyncAction = null,
        protected ?SentenceBasedShotDistributor $sentenceDistributor = null,
        protected ?SynthesizeBroadcastMasterAudioAction $masterAudioAction = null,
        protected ?GenerateBroadcastVisualsBatchAction $visualsBatchAction = null,
    ) {}

    /**
     * Inicializa el flujo headless creando Project, Scene, ScriptBlock y BroadcastJob en estado RAW,
     * y despacha la siguiente etapa asíncrona hacia la cola de broadcast.
     */
    public function initialize(BroadcastJobRequestDTO $dto): BroadcastJob
    {
        $job = DB::transaction(function () use ($dto): BroadcastJob {
            [$project, $block] = $this->createProjectAndBlock($dto);

            $artifacts = array_filter([
                'resolution_profile' => $dto->resolutionProfile,
                'output_relative_path' => $dto->outputRelativePath,
                'cast_profiles' => $dto->castProfiles !== [] ? $dto->castProfiles : null,
                'location_profile' => $dto->locationProfile,
                'tts_voice' => $dto->ttsVoice,
                'tts_provider' => $dto->ttsProvider,
                'visual_credit' => $dto->visualCredit,
                'publish_to_podcast' => $dto->publishToPodcast,
                'generate_short' => $dto->generateShort,
            ], static fn (mixed $value): bool => $value !== null);

            $job = BroadcastJob::create([
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
                'artifacts' => $artifacts,
            ]);

            if ($dto->castProfiles !== [] || $dto->locationProfile !== null) {
                $artifacts['bible_summary'] = $this->syncBible($job, $project);
                $job->update(['artifacts' => $artifacts]);
            }

            return $job;
        });

        RunBroadcastStageJob::dispatch($job->id, BroadcastJobStage::TREATMENT)->onQueue('broadcast');

        return $job;
    }

    private function createProjectAndBlock(BroadcastJobRequestDTO $dto): array
    {
        $defaultSlugline = in_array(strtolower(trim($dto->format)), ['bio', 'pill', 'pildora'], true)
            ? 'EXT. DOCUMENTAL TEMÁTICO - DÍA'
            : 'INT. ESTUDIO BROADCAST - DÍA';

        $existingProject = $dto->projectId !== null
            ? Project::query()->find($dto->projectId)
            : null;

        if ($existingProject instanceof Project) {
            $existingProject->update([
                'status' => 'in_progress',
                'category' => ProjectCategory::AI_VIDEO->value,
                'storyboard_style' => $dto->visualMode,
            ]);

            $scene = $existingProject->scenes()->orderBy('scene_order')->first() ?? Scene::create([
                'project_id' => $existingProject->id,
                'scene_order' => 1,
                'slugline' => $defaultSlugline,
                'time_of_day' => 'DÍA',
            ]);

            $block = $scene->scriptBlocks()->orderBy('block_order')->first();
            $rawText = $this->buildContextualizedRawText($dto);

            if ($block instanceof ScriptBlock) {
                $block->shots()->delete();
                $block->update([
                    'raw_text' => $rawText,
                    'structured_text' => null,
                    'screenplay_content' => null,
                    'stage' => BlockStage::RAW->value,
                ]);
            } else {
                $block = ScriptBlock::create([
                    'scene_id' => $scene->id,
                    'block_order' => 1,
                    'stage' => BlockStage::RAW->value,
                    'raw_text' => $rawText,
                ]);
            }

            return [$existingProject, $block];
        }

        $project = Project::create([
            'user_id' => $this->resolveBroadcastUser()->id,
            'title' => $dto->title !== '' ? $dto->title : 'Broadcast '.now()->format('Y-m-d H:i:s'),
            'status' => 'in_progress',
            'category' => ProjectCategory::AI_VIDEO->value,
            'storyboard_style' => $dto->visualMode,
        ]);

        $scene = Scene::create([
            'project_id' => $project->id,
            'scene_order' => 1,
            'slugline' => $defaultSlugline,
            'time_of_day' => 'DÍA',
        ]);

        return [$project, ScriptBlock::create([
            'scene_id' => $scene->id,
            'block_order' => 1,
            'stage' => BlockStage::RAW->value,
            'raw_text' => $this->buildContextualizedRawText($dto),
        ])];
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
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $activeIds = array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_array($item) && ! empty($item['shot_id']) ? (string) $item['shot_id'] : null,
            is_array($artifacts['shots'] ?? null) ? $artifacts['shots'] : []
        )));
        $shots = $block->shots()
            ->when($activeIds !== [], static fn ($query) => $query->whereIn('id', $activeIds))
            ->orderBy('shot_number', 'asc')
            ->get();

        if ($shots->isEmpty()) {
            throw new RuntimeException("No shots available to render for broadcast job {$job->id}.");
        }

        if (empty($artifacts['master_audio'])) {
            ($this->masterAudioAction ?? app(SynthesizeBroadcastMasterAudioAction::class))->execute($job);
            $job->refresh();
            $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        }

        if (empty($artifacts['shot_visuals'])) {
            ($this->visualsBatchAction ?? app(GenerateBroadcastVisualsBatchAction::class))->execute($job);
            $job->refresh();
            $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        }

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
        $artifacts['bible_summary'] = $this->syncBible($job, $job->project);
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
        $artifacts['bible_summary'] = $this->syncBible($job, $job->project);
        $job->update([
            'stage' => BroadcastJobStage::SCREENPLAY,
            'artifacts' => $artifacts,
        ]);

        RunBroadcastStageJob::dispatch($job->id, BroadcastJobStage::SHOTS)->onQueue('broadcast');
    }

    protected function processShotsStage(BroadcastJob $job, ScriptBlock $block, array $artifacts): void
    {
        $shots = $this->pipeline->generateShotsBreakdown($block);
        if ($shots === []) {
            $shots = $this->createDeterministicFallbackShots($job, $block);
        }
        $sanitized = $this->sanitizer->sanitizeAndFormatShots($shots);
        $distributor = $this->sentenceDistributor ?? app(SentenceBasedShotDistributor::class);
        $explicitCredit = isset($artifacts['visual_credit']) && is_string($artifacts['visual_credit'])
            ? $artifacts['visual_credit']
            : null;
        $distributed = $distributor->distribute($sanitized, (string) $job->raw_text, $explicitCredit);

        $artifacts['shots'] = $distributed['shots'];
        $artifacts['shots_count'] = count($distributed['shots']);
        $artifacts['full_oral_text'] = $distributed['full_oral_text'];
        if ($distributed['visual_credit'] !== null) {
            $artifacts['visual_credit'] = $distributed['visual_credit'];
        }

        $job->update([
            'stage' => BroadcastJobStage::SHOTS,
            'artifacts' => $artifacts,
        ]);

        $rundown = TvRundown::query()->where('broadcast_job_id', $job->id)->first();
        if ($rundown !== null && (string) $rundown->stage === 'queued') {
            $this->startRendering($job);
            $rundown->update(['stage' => 'rendering', 'status' => 'rendering']);
        }
    }

    /**
     * Crea planos deterministas en BD a partir de las frases del guion si el detector LLM falla.
     *
     * @return list<Shot>
     */
    private function createDeterministicFallbackShots(BroadcastJob $job, ScriptBlock $block): array
    {
        $distributor = $this->sentenceDistributor ?? app(SentenceBasedShotDistributor::class);
        $seed = array_map(
            static fn (int $i): array => ['shot_number' => $i, 'shot_type' => $i === 1 ? 'PG' : 'PM'],
            range(1, 12)
        );
        $distributed = $distributor->distribute($seed, (string) $job->raw_text);
        $created = [];
        $framings = ['PG', 'PM', 'PP', 'PA'];

        foreach ($distributed['shots'] as $index => $item) {
            $num = $index + 1;
            $oral = trim((string) ($item['oral_text'] ?? ''));
            if ($oral === '') {
                continue;
            }
            $created[] = Shot::create([
                'scene_id' => $block->scene_id,
                'script_block_id' => $block->id,
                'shot_number' => $num,
                'shot_type' => $framings[$index % count($framings)],
                'description' => "Escena visual ilustrativa sobre {$job->title}: {$oral}",
                'estimated_duration' => max(5, (int) ($item['estimated_seconds'] ?? 8)),
            ]);
        }

        $block->update(['stage' => BlockStage::SHOTS->value]);

        return $created;
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
        $formatDescription = match (strtolower(trim($dto->format))) {
            'bio', 'pill', 'pildora' => 'Píldora informativa ágil en voz en off (formato documental visual, SIN presentador en cámara, SIN prefijos HOST/LOCUTOR; cada plano describe una escena visual ilustrativa del tema)',
            'news', 'noticia' => 'Crónica periodística televisiva con tono informativo riguroso',
            'debate' => 'Mesa de debate y contraste dialéctico con dinamismo oral',
            default => 'Reportaje audiovisual ágil',
        };

        $langInstruction = strtolower(trim($dto->language)) === 'en'
            ? 'Language: English. Natural oral phrasing for high-speed speech.'
            : 'Idioma: Español. Mantener narración natural para locución oral de broadcast.';

        return "[FORMATO: {$formatDescription}]\n"
            ."[PAUTA: {$langInstruction}]\n"
            ."[TÍTULO: {$dto->title}]\n\n"
            .trim($dto->rawText);
    }

    private function syncBible(BroadcastJob $job, ?Project $project = null): array
    {
        return ($this->bibleSyncAction ?? app(SyncBroadcastProjectBibleAction::class))->execute($job, $project);
    }
}
