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
            'storyboard', 'full_bleed', 'custom_style', 'grayscale_sketch', 'kids_2d_animation', 'enki_bilal', 'editorial_documentary_35mm', 'clean_editorial_illustration', 'cinematic_tech_minimal', 'scifi_painterly' => $this->storyboardVisual,
            default => $this->staticCardVisual,
        };
    }
}
