<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Models\BroadcastJob;
use App\Models\Shot;
use App\Models\TvRundown;
use App\Models\TvVisualStyle;
use App\Services\Broadcast\OralShotSanitizer;
use App\Services\StoryboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final readonly class UpdateRundownShotsAction
{
    public function __construct(
        private StoryboardService $storyboardService,
        private AutoGenerateBroadcastShotsAction $broadcastAction,
        private OralShotSanitizer $sanitizer,
    ) {}

    /**
     * Load editable shots for a rundown ordered by shot_number.
     *
     * @return list<array{shot_id: string, shot_number: int, shot_type: string, description: string, oral_text: string, has_image: bool, storyboard_url: ?string}>
     */
    public function loadEditableShots(TvRundown $rundown): array
    {
        $job = $rundown->broadcastJob;
        $block = $job?->scriptBlock()->first();
        if ($job === null || $block === null) {
            return [];
        }

        $shotContexts = $this->indexShotContexts(is_array($job->artifacts) ? $job->artifacts : []);
        $activeIds = array_keys($shotContexts);
        $shots = $block->shots()
            ->when($activeIds !== [], static fn ($query) => $query->whereIn('id', $activeIds))
            ->orderBy('shot_number', 'asc')
            ->get();

        $result = [];
        foreach ($shots as $shot) {
            $shotId = (string) $shot->id;
            $hasImage = ! empty($shot->storyboard_image_path);
            $versionHash = $hasImage ? substr(sha1((string) $shot->storyboard_image_path), 0, 12) : null;

            $result[] = [
                'shot_id' => $shotId,
                'shot_number' => (int) $shot->shot_number,
                'shot_type' => (string) ($shot->shot_type ?? ''),
                'description' => (string) ($shot->description ?? ''),
                'oral_text' => (string) ($shotContexts[$shotId]['oral_text'] ?? ''),
                'has_image' => $hasImage,
                'storyboard_url' => $hasImage ? route('tv.rundowns.shot-storyboard', ['rundown' => $rundown->id, 'shot' => $shot->id, 'v' => $versionHash]) : null,
            ];
        }

        return $result;
    }

    /**
     * Regenerate the storyboard visual for a single shot and invalidate only its visual/clip artifacts.
     */
    public function regenerateSingleShotVisual(
        TvRundown $rundown,
        string $shotId,
        ?string $newDescription = null,
    ): Shot {
        $job = $this->ensureEditableBroadcastJob($rundown);

        $shot = Shot::query()->with('scene.project')->find($shotId);
        if (! $shot instanceof Shot) {
            throw new RuntimeException("No se encontró el plano {$shotId}.", 404);
        }

        $belongsToBlock = $job->script_block_id !== null
            && (string) $shot->script_block_id === (string) $job->script_block_id;
        $belongsToProject = $rundown->project_id !== null
            && (string) ($shot->scene?->project_id ?? '') === (string) $rundown->project_id;

        if (! $belongsToBlock && ! $belongsToProject) {
            throw new InvalidArgumentException("El plano {$shotId} no pertenece a la escaleta {$rundown->id}.", 422);
        }

        if ($newDescription !== null) {
            $trimmedDescription = trim($newDescription);
            if (mb_strlen($trimmedDescription) > 2000) {
                throw new InvalidArgumentException('La descripción visual no puede superar los 2000 caracteres.', 422);
            }
            if ($trimmedDescription !== '') {
                $shot->update(['description' => $trimmedDescription]);
            }
        }

        $shot->loadMissing('scene.project.visualStyle');
        $project = $rundown->project ?? $shot->scene?->project;
        $aestheticData = is_array($shot->visual_aesthetic_config) && $shot->visual_aesthetic_config !== []
            ? $shot->visual_aesthetic_config
            : (is_array($project?->visual_aesthetic_config) ? $project->visual_aesthetic_config : []);
        $hasCustomStyle = $rundown->visual_style_id !== null
            || $project?->visualStyle instanceof TvVisualStyle
            || $project?->visual_style_id !== null
            || (is_string($aestheticData['style_prompt'] ?? null) && trim((string) $aestheticData['style_prompt']) !== '');

        $rawMode = trim((string) $job->visual_mode);
        $style = $hasCustomStyle
            ? 'custom_style'
            : (($rawMode !== '' && array_key_exists($rawMode, StoryboardService::STYLES))
                ? $rawMode
                : 'editorial_documentary_35mm');

        $aestheticConfig = VisualAestheticConfigDTO::fromArray(
            $aestheticData,
            VisualAestheticConfigDTO::MODE_FULL_BLEED,
        );
        $styleReferenceBase64 = $this->resolveStyleReferenceBase64($rundown, $shot);

        $generatedPath = $this->storyboardService->generateStoryboard(
            shot: $shot,
            style: $style,
            aestheticConfig: $aestheticConfig,
            styleReferenceImageBase64: $styleReferenceBase64,
        );

        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        if (! isset($artifacts['shot_visuals']) || ! is_array($artifacts['shot_visuals'])) {
            $artifacts['shot_visuals'] = [];
        }
        $artifacts['shot_visuals'][$shotId] = $this->resolveExistingStoryboardPath($generatedPath) ?? $generatedPath;

        if (isset($artifacts['rendered_shots']) && is_array($artifacts['rendered_shots'])) {
            unset($artifacts['rendered_shots'][$shotId]);
        }

        $job->update(['artifacts' => $artifacts]);

        return $shot->refresh();
    }

    /**
     * Update oral texts for edited shots and invalidate master audio and rendered clips.
     *
     * @param  array<string, string>  $shotOralTexts
     */
    public function updateOralTextsAndInvalidateAudio(TvRundown $rundown, array $shotOralTexts): BroadcastJob
    {
        $job = $this->ensureEditableBroadcastJob($rundown);

        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $shots = is_array($artifacts['shots'] ?? null) ? $artifacts['shots'] : [];
        $oralParts = [];

        foreach ($shots as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $shotId = (string) ($entry['shot_id'] ?? '');
            if ($shotId !== '' && array_key_exists($shotId, $shotOralTexts)) {
                $rawOral = (string) $shotOralTexts[$shotId];
                if (mb_strlen(trim($rawOral)) > 4000) {
                    throw new InvalidArgumentException('El texto oral de un shot no puede superar los 4000 caracteres.', 422);
                }

                $cleanOralText = $this->sanitizer->stripMarkdown($rawOral);
                $words = preg_split('/\s+/u', trim($cleanOralText), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                $entry['oral_text'] = $cleanOralText;
                $entry['word_count'] = count($words);
                $entry['estimated_seconds'] = $this->sanitizer->estimateSeconds($cleanOralText);
                $shots[$index] = $entry;
            }

            $oralText = trim((string) ($entry['oral_text'] ?? ''));
            if ($oralText !== '') {
                $oralParts[] = $oralText;
            }
        }

        $fullOralText = implode(' ', $oralParts);
        if (trim($fullOralText) === '') {
            throw new InvalidArgumentException('La escaleta debe conservar texto oral en al menos un shot.', 422);
        }
        $artifacts['shots'] = $shots;
        $artifacts['full_oral_text'] = $fullOralText;
        unset($artifacts['master_audio'], $artifacts['rendered_shots']);

        DB::transaction(static function () use ($job, $rundown, $fullOralText, $artifacts): void {
            $job->update([
                'raw_text' => $fullOralText,
                'artifacts' => $artifacts,
            ]);

            $rundown->update([
                'script_text' => $fullOralText,
            ]);
        });

        return $job->refresh();
    }

    /**
     * Reassemble the final broadcast video reusing unmodified assets.
     */
    public function reassembleAfterEdits(TvRundown $rundown): TvRundown
    {
        $job = $this->ensureEditableBroadcastJob($rundown);
        $this->pruneMissingRenderedClips($job);

        DB::transaction(static function () use ($rundown): void {
            $rundown->update([
                'stage' => 'rendering',
                'status' => 'rendering',
                'error_message' => null,
            ]);

            $rundown->tvVideo()->update([
                'stage' => 'rendering',
                'status' => 'rendering',
                'error_message' => null,
            ]);
        });

        $this->broadcastAction->startRendering($job);

        return $rundown->refresh();
    }

    private function ensureEditableBroadcastJob(TvRundown $rundown): BroadcastJob
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('La escaleta tiene un proceso activo y está bloqueada hasta finalizar.', 409);
        }

        $job = $rundown->broadcastJob;
        if (! $job instanceof BroadcastJob) {
            throw new RuntimeException("La escaleta {$rundown->id} no tiene un BroadcastJob asociado.", 422);
        }

        return $job;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     * @return array<string, array<string, mixed>>
     */
    private function indexShotContexts(array $artifacts): array
    {
        $rawShots = is_array($artifacts['shots'] ?? null) ? $artifacts['shots'] : [];
        $indexed = [];

        foreach ($rawShots as $item) {
            if (! is_array($item) || empty($item['shot_id'])) {
                continue;
            }

            $indexed[(string) $item['shot_id']] = $item;
        }

        return $indexed;
    }

    private function pruneMissingRenderedClips(BroadcastJob $job): void
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $renderedShots = $artifacts['rendered_shots'] ?? null;
        if (! is_array($renderedShots) || $renderedShots === []) {
            return;
        }

        $pruned = [];
        $changed = false;
        foreach ($renderedShots as $shotId => $entry) {
            $clipPath = is_array($entry) ? ($entry['clip_path'] ?? null) : (is_string($entry) ? $entry : null);
            if (is_string($clipPath) && $clipPath !== '' && is_file($clipPath)) {
                $pruned[$shotId] = $entry;
            } else {
                $changed = true;
            }
        }

        if ($changed) {
            $artifacts['rendered_shots'] = $pruned;
            $job->update(['artifacts' => $artifacts]);
        }
    }

    private function resolveStyleReferenceBase64(TvRundown $rundown, Shot $shot): ?string
    {
        if ($shot->shot_number <= 1) {
            $rundown->loadMissing('visualStyle', 'project.visualStyle');
            $referencePath = ($rundown->visualStyle ?? $rundown->project?->visualStyle)?->reference_image_path;
            if (! is_string($referencePath) || trim($referencePath) === '' || ! Storage::disk('local')->exists($referencePath)) {
                return null;
            }
            $bytes = Storage::disk('local')->get($referencePath);

            return is_string($bytes) && $bytes !== '' ? base64_encode($bytes) : null;
        }
        if ($shot->scene_id === null && $shot->script_block_id === null) {
            return null;
        }
        $anchorShot = Shot::query()
            ->when($shot->scene_id !== null, fn ($q) => $q->where('scene_id', $shot->scene_id))
            ->when($shot->scene_id === null && $shot->script_block_id !== null, fn ($q) => $q->where('script_block_id', $shot->script_block_id))
            ->where('shot_number', 1)->whereKeyNot($shot->id)->first();
        $anchorPath = $this->resolveExistingStoryboardPath($anchorShot?->storyboard_image_path);
        $bytes = ($anchorPath !== null && is_readable($anchorPath)) ? file_get_contents($anchorPath) : false;

        return is_string($bytes) && $bytes !== '' ? base64_encode($bytes) : null;
    }

    private function resolveExistingStoryboardPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        return is_file($path) ? $path : null;
    }
}
