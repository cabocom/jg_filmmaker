<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Visuals;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;
use App\Models\BroadcastJob;
use App\Models\Shot;
use RuntimeException;

final class GenerativeVideoShotVisual implements ShotVisualStrategyInterface
{
    public function visualType(): string
    {
        return 'video';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function generateVisual(BroadcastJob $job, Shot $shot, array $context = []): string
    {
        $clipPath = $context['clip_path'] ?? null;

        if (is_string($clipPath) && $clipPath !== '' && file_exists($clipPath)) {
            return $clipPath;
        }

        throw new RuntimeException('GenerativeVideoShotVisual requiere un proveedor de vídeo IA configurado o clip_path pre-renderizado.');
    }
}
