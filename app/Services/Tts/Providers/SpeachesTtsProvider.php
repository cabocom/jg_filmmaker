<?php

declare(strict_types=1);

namespace App\Services\Tts\Providers;

use App\Services\Tts\Contracts\TtsProviderInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SpeachesTtsProvider implements TtsProviderInterface
{
    public function __construct(
        protected string $speachesUrl,
        protected string $model,
        protected int $timeout = 30
    ) {}

    public function isAvailable(): bool
    {
        return ! empty($this->speachesUrl);
    }

    public function synthesize(string $cleanText, string $voice, float $speed, string $cachedFilePath): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'audio/wav',
                ])
                ->post($this->speachesUrl.'/v1/audio/speech', [
                    'model' => $this->model,
                    'input' => $cleanText,
                    'voice' => $voice,
                    'response_format' => 'wav',
                    'speed' => $speed,
                ]);

            if (! $response->successful()) {
                Log::error('Fallo en síntesis TTS con Speaches', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'text_snippet' => mb_substr($cleanText, 0, 80),
                    'voice' => $voice,
                ]);

                throw new RuntimeException('Error al contactar con el servicio de voz Speaches: '.$response->status());
            }

            File::put($cachedFilePath, $response->body());

            return $cachedFilePath;
        } catch (\Throwable $e) {
            Log::error('Excepción al sintetizar voz vía Speaches', [
                'message' => $e->getMessage(),
                'voice' => $voice,
                'endpoint' => $this->speachesUrl,
            ]);

            throw new RuntimeException('No se pudo generar la locución: '.$e->getMessage(), 0, $e);
        }
    }
}
