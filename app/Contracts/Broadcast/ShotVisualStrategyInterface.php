<?php

declare(strict_types=1);

namespace App\Contracts\Broadcast;

use App\Models\BroadcastJob;
use App\Models\Shot;

interface ShotVisualStrategyInterface
{
    /**
     * Genera el elemento visual para el shot y devuelve su ruta absoluta en disco.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateVisual(BroadcastJob $job, Shot $shot, array $context = []): string;

    /**
     * Retorna el tipo de visual ('image' o 'video').
     */
    public function visualType(): string;
}
