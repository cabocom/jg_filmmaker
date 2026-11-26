<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Visuals;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;
use App\DTOs\Storyboard\VisualAestheticConfigDTO;
use App\Models\BroadcastJob;
use App\Models\Shot;
use App\Services\StoryboardService;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class StoryboardShotVisual implements ShotVisualStrategyInterface
{
    public function __construct(
        protected StoryboardService $storyboardService,
        protected StaticCardShotVisual $fallbackVisual
    ) {}

    public function visualType(): string
    {
        return 'image';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function generateVisual(BroadcastJob $job, Shot $shot, array $context = []): string
    {
        $existingPath = $this->resolveExistingPath((string) $shot->storyboard_image_path);
        if ($existingPath !== null) {
            return $existingPath;
        }

        try {
            $rawMode = trim((string) $job->visual_mode);
            $visualMode = ($rawMode !== '' && array_key_exists($rawMode, StoryboardService::STYLES))
                ? $rawMode
                : 'editorial_documentary_35mm';

            $shot->loadMissing('scene.project');
            $rawAesthetic = is_array($shot->visual_aesthetic_config) && $shot->visual_aesthetic_config !== []
                ? $shot->visual_aesthetic_config
                : (is_array($shot->scene?->project?->visual_aesthetic_config) ? $shot->scene->project->visual_aesthetic_config : []);
            $aestheticConfig = VisualAestheticConfigDTO::fromArray($rawAesthetic, VisualAestheticConfigDTO::MODE_FULL_BLEED);
            $styleReferenceBase64 = $this->resolveStyleReferenceBase64($shot);

            $generatedRelative = $this->storyboardService->generateStoryboard(
                shot: $shot,
                style: $visualMode,
                aestheticConfig: $aestheticConfig,
                styleReferenceImageBase64: $styleReferenceBase64,
            );
            $resolved = $this->resolveExistingPath($generatedRelative);

            if ($resolved !== null) {
                return $resolved;
            }
        } catch (Throwable) {
            // Fallback en caso de fallo en agente, tokens o modo mock sin fichero
        }

        return $this->fallbackVisual->generateVisual($job, $shot, $context);
    }

    private function resolveStyleReferenceBase64(Shot $shot): ?string
    {
        if ($shot->shot_number <= 1 || ($shot->scene_id === null && $shot->script_block_id === null)) {
            return null;
        }

        $anchorShot = Shot::query()
            ->when($shot->scene_id !== null, fn ($q) => $q->where('scene_id', $shot->scene_id))
            ->when($shot->scene_id === null && $shot->script_block_id !== null, fn ($q) => $q->where('script_block_id', $shot->script_block_id))
            ->where('shot_number', 1)
            ->whereKeyNot($shot->id)
            ->first();

        if (! $anchorShot || empty($anchorShot->storyboard_image_path)) {
            return null;
        }

        $anchorPath = $this->resolveExistingPath($anchorShot->storyboard_image_path);
        if ($anchorPath === null || ! is_readable($anchorPath)) {
            return null;
        }

        $bytes = file_get_contents($anchorPath);

        return is_string($bytes) && $bytes !== '' ? base64_encode($bytes) : null;
    }

    private function resolveExistingPath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }
}
