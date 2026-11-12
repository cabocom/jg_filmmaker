<?php

declare(strict_types=1);

namespace App\Services\Tts\Contracts;

interface TtsProviderInterface
{
    /**
     * Sintetiza el texto limpio en la ruta de caché indicada.
     */
    public function synthesize(string $cleanText, string $voice, float $speed, string $cachedFilePath): string;

    /**
     * Determina si el proveedor está habilitado y configurado para operar.
     */
    public function isAvailable(): bool;
}
