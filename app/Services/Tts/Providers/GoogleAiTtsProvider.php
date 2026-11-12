<?php

declare(strict_types=1);

namespace App\Services\Tts\Providers;

use App\Services\Tts\Contracts\TtsProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleAiTtsProvider implements TtsProviderInterface
{
    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl,
        protected string $defaultModel,
        protected int $timeout = 30
    ) {}

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function synthesize(string $cleanText, string $voice, float $speed, string $cachedFilePath): string
    {
        $candidateModels = $this->resolveCandidateModels();
        $this->resetExhaustedIfAllBlocked($candidateModels);

        $response = null;
        $lastErrorStatus = null;

        foreach ($candidateModels as $model) {
            $exhaustedKey = "tts_model_exhausted_{$model}";
            if (Cache::has($exhaustedKey)) {
                continue;
            }

            $res = $this->tryModelRequest($model, $cleanText, $voice, $exhaustedKey);
            if ($res === 'success') {
                $response = $this->lastSuccessfulResponse;
                break;
            }
            if ($res === '429') {
                $lastErrorStatus = 429;
            }
        }

        if (! $response || ! $response->successful()) {
            Log::error('Fallo en síntesis TTS con todos los modelos de Google AI', ['status' => $lastErrorStatus, 'voice' => $voice]);
            throw new RuntimeException('Error al contactar con Google AI TTS: '.($lastErrorStatus ?? 'desconocido'));
        }

        return $this->extractAndSaveAudio($response, $cachedFilePath);
    }

    protected ?Response $lastSuccessfulResponse = null;

    protected function resolveCandidateModels(): array
    {
        return array_values(array_unique([
            $this->defaultModel,
            'gemini-3.8-flash-lite-tts',
            'gemini-2.5-flash-preview-tts',
            'gemini-2.5-pro-preview-tts',
            'gemini-3.1-flash-tts-preview',
        ]));
    }

    protected function resetExhaustedIfAllBlocked(array $models): void
    {
        $allExhausted = collect($models)->every(fn ($m) => Cache::has("tts_model_exhausted_{$m}"));
        if ($allExhausted) {
            foreach ($models as $m) {
                Cache::forget("tts_model_exhausted_{$m}");
            }
        }
    }

    protected function tryModelRequest(string $model, string $cleanText, string $voice, string $exhaustedKey): string
    {
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $this->buildPayload($cleanText, $voice));

            if ($response->status() === 429) {
                $this->handleRateLimit($model, $exhaustedKey, (string) $response->body());

                return '429';
            }

            if ($response->successful()) {
                $this->lastSuccessfulResponse = $response;

                return 'success';
            }

            return (string) $response->status();
        } catch (\Throwable $e) {
            Log::warning("Error de conexión con modelo {$model}: ".$e->getMessage());

            return 'error';
        }
    }

    protected function handleRateLimit(string $model, string $exhaustedKey, string $body): void
    {
        $isDailyQuota = str_contains($body, 'per_model_per_day')
            || str_contains($body, 'generate_requests_per_model_per_day')
            || str_contains($body, 'requests_per_day')
            || (str_contains($body, 'limit: 100') && str_contains($body, 'day'));

        if ($isDailyQuota) {
            Cache::put($exhaustedKey, true, now()->addMinutes(60));
            Log::warning("Cuota diaria agotada (HTTP 429) en Google AI modelo {$model}. Conmutando a modelos de reserva...");
        } else {
            Log::warning("Rate limit por minuto (HTTP 429) en Google AI modelo {$model}. Probando modelo alternativo...");
            usleep(300000);
        }
    }

    protected function buildPayload(string $cleanText, string $voice): array
    {
        return [
            'contents' => [['parts' => [['text' => $cleanText]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]]],
            ],
        ];
    }

    protected function extractAndSaveAudio(Response $response, string $cachedFilePath): string
    {
        $parts = $response->json('candidates.0.content.parts') ?? [];
        $base64Data = collect($parts)->first(fn ($p) => ! empty($p['inlineData']['data']))['inlineData']['data'] ?? null;

        if (! $base64Data) {
            throw new RuntimeException('Google AI TTS no devolvió datos de audio válidos.');
        }

        File::put($cachedFilePath, base64_decode($base64Data));

        return $cachedFilePath;
    }
}
