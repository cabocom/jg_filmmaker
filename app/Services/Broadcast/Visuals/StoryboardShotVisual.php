<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Visuals;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;
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
            $visualMode = (string) ($job->visual_mode ?: 'grayscale_sketch');
            $generatedRelative = $this->storyboardService->generateStoryboard($shot, $visualMode);
            $resolved = $this->resolveExistingPath($generatedRelative);

            if ($resolved !== null) {
                return $resolved;
            }
        } catch (Throwable) {
            // Fallback en caso de fallo en agente, tokens o modo mock sin fichero
        }

        return $this->fallbackVisual->generateVisual($job, $shot, $context);
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
