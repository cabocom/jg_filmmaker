<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Visuals;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;

final readonly class ShotVisualStrategyResolver
{
    public function __construct(
        protected StaticCardShotVisual $staticCardVisual,
        protected StoryboardShotVisual $storyboardVisual,
        protected GenerativeVideoShotVisual $generativeVideoVisual
    ) {}

    public function resolve(string $visualMode): ShotVisualStrategyInterface
    {
        return match ($visualMode) {
            'generative_video', 'ai_video' => $this->generativeVideoVisual,
            'storyboard', 'grayscale_sketch', 'kids_2d_animation', 'enki_bilal' => $this->storyboardVisual,
            default => $this->staticCardVisual,
        };
    }
}
