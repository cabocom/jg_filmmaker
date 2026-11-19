<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Project;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\Tts\Contracts\TtsProviderInterface;
use App\Services\Tts\Providers\GoogleAiTtsProvider;
use App\Services\Tts\Providers\SpeachesTtsProvider;
use App\Services\Tts\ScreenplaySegmenter;
use App\Services\Tts\TtsManifestService;
use App\Services\Tts\TtsVoiceCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TextToSpeechService
{
    protected string $speachesUrl;

    protected string $model;

    protected string $defaultVoice;

    protected int $timeout;

    protected string $cacheDir;

    protected ?string $googleApiKey;

    protected string $googleAiBaseUrl;

    protected string $googleAiTtsModel;

    protected TtsProviderInterface $googleAiProvider;

    protected TtsProviderInterface $speachesProvider;

    protected ScreenplaySegmenter $segmenter;

    protected TtsManifestService $manifestService;

    protected ?AgentTelemetryService $telemetryService = null;

    public function __construct(?AgentTelemetryService $telemetryService = null)
    {
        $this->telemetryService = $telemetryService ?? app(AgentTelemetryService::class);
        $this->speachesUrl = rtrim((string) config('services.speaches.url', 'http://speaches:8000'), '/');
        $this->model = (string) config('services.speaches.model', 'speaches-ai/piper-es_ES-sharvard-medium');
        $this->googleApiKey = config('services.google_ai.api_key') ?: null;
        $this->googleAiBaseUrl = rtrim((string) config('services.google_ai.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->googleAiTtsModel = (string) config('services.google_ai.tts_model', 'gemini-3.8-flash-tts');
        $this->defaultVoice = $this->googleApiKey ? 'Puck' : (string) config('services.speaches.default_voice', 'sharvard#M');
        $this->timeout = (int) config('services.speaches.timeout', 30);
        $this->cacheDir = storage_path('tts_cache');

        if (! File::exists($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0775, true, true);
        }

        $this->googleAiProvider = new GoogleAiTtsProvider($this->googleApiKey, $this->googleAiBaseUrl, $this->googleAiTtsModel, $this->timeout);
        $this->speachesProvider = new SpeachesTtsProvider($this->speachesUrl, $this->model, $this->timeout);
        $this->segmenter = new ScreenplaySegmenter($this->defaultVoice, ! empty($this->googleApiKey));
        $this->manifestService = new TtsManifestService($this, $this->segmenter, $this->cacheDir);
    }

    public function getDefaultVoice(): string
    {
        return $this->defaultVoice;
    }

    /**
     * @return array<int, array{id: string, name: string, gender: string, lang: string}>
     */
    public function getAvailableVoices(): array
    {
        return TtsVoiceCatalog::getAvailableVoices($this->googleApiKey);
    }

    public function resolveVoice(?string $voice = null): string
    {
        return TtsVoiceCatalog::resolveVoice($voice, $this->defaultVoice, $this->googleApiKey);
    }

    public function synthesize(
        string $text,
        ?string $voice = null,
        float $speed = 1.0,
        ?Project $project = null,
        ?User $user = null
    ): string {
        $cleanText = $this->expandScreenplayAbbreviations(trim($text));
        if ($cleanText === '') {
            throw new RuntimeException('El texto a sintetizar no puede estar vacío.');
        }

        $activeVoice = $this->resolveVoice($voice);
        $cachedFilePath = $this->getCachedAudioPath($cleanText, $activeVoice, $speed);
        if (File::exists($cachedFilePath) && File::size($cachedFilePath) > 0) {
            return $cachedFilePath;
        }

        if (app()->environment('testing') && ! Http::hasMacro('tts_testing_real')) {
            File::put($cachedFilePath, base64_decode('UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='));

            return $cachedFilePath;
        }

        return $this->executeSynthesisPipeline($cleanText, $activeVoice, $speed, $cachedFilePath, $project, $user);
    }

    protected function executeSynthesisPipeline(
        string $cleanText,
        string $activeVoice,
        float $speed,
        string $cachedFilePath,
        ?Project $project = null,
        ?User $user = null
    ): string {
        $currentUser = $user ?? (auth()->check() ? auth()->user() : ($project?->user ?? null));
        $estimatedTokens = max(10, (int) ceil(mb_strlen($cleanText) / 4));
        $isByok = false;
        $activeGoogleProvider = $this->googleAiProvider;

        if ($currentUser && $currentUser->hasActiveByokCredential('google')) {
            $byokCred = $currentUser->getActiveCredential('google');
            if ($byokCred) {
                $isByok = true;
                $activeGoogleProvider = new GoogleAiTtsProvider(
                    $byokCred->getDecryptedKey(),
                    $this->googleAiBaseUrl,
                    $this->googleAiTtsModel,
                    $this->timeout
                );
            }
        }

        if ($activeGoogleProvider->isAvailable()) {
            if (! $isByok && $currentUser && ! $currentUser->hasDailyTokensAvailable($estimatedTokens)) {
                Log::warning("Cuota diaria de tokens agotada para usuario {$currentUser->id} en Google AI TTS. Conmutando a fallback...");
            } else {
                try {
                    $path = $activeGoogleProvider->synthesize($cleanText, $activeVoice, $speed, $cachedFilePath);
                    if ($this->telemetryService && ($project || $currentUser)) {
                        $this->telemetryService->record(
                            projectId: $project?->id ?? '00000000-0000-0000-0000-000000000000',
                            scriptBlockId: null,
                            toolName: 'google_ai_tts',
                            inputPayload: ['text' => $cleanText, 'voice' => $activeVoice, 'speed' => $speed],
                            outputPayload: ['cached_file' => basename($cachedFilePath), 'size_bytes' => File::size($cachedFilePath)],
                            telemetry: method_exists($activeGoogleProvider, 'getLastTelemetry') ? $activeGoogleProvider->getLastTelemetry() : null,
                            userId: $currentUser?->id,
                            isByok: $isByok
                        );
                    }

                    if ($project) {
                        $this->manifestService->invalidateAudioCompleteCache($project);
                    }

                    return $path;
                } catch (\Throwable $e) {
                    Log::warning("Google AI TTS no pudo sintetizar [{$cleanText}]: {$e->getMessage()}. Intentando fallback...");
                }
            }
        }

        try {
            $result = $this->speachesProvider->synthesize($cleanText, $activeVoice, $speed, $cachedFilePath);
            if ($project) {
                $this->manifestService->invalidateAudioCompleteCache($project);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("Speaches TTS no disponible o falló: {$e->getMessage()}.");
            throw new RuntimeException("Servicio de síntesis de voz (TTS) no disponible: {$e->getMessage()}", 0, $e);
        }
    }

    public function expandScreenplayAbbreviations(string $text): string
    {
        return $this->segmenter->expandScreenplayAbbreviations($text);
    }

    public function parseBlockToSegments(
        ScriptBlock $block,
        Collection|array $characters = [],
        ?Project $project = null,
        bool $persistBibleChanges = false
    ): array {
        return $this->segmenter->parseBlockToSegments($block, $characters, $project, $persistBibleChanges);
    }

    public function buildManifestForScope(
        Project $project,
        ?string $sceneId = null,
        ?string $blockId = null,
        bool $persistBibleChanges = false
    ): array {
        return $this->manifestService->buildManifestForScope($project, $sceneId, $blockId, $persistBibleChanges);
    }

    public function getCachedAudioPath(string $text, ?string $voice = null, float $speed = 1.0): string
    {
        $cleanText = $this->expandScreenplayAbbreviations(trim($text));
        $activeVoice = $this->resolveVoice($voice);
        $providerKey = $this->googleApiKey ? 'google_ai_'.$this->googleAiTtsModel : $this->model;
        $hash = hash('sha256', $providerKey.'|'.$activeVoice.'|'.number_format($speed, 2, '.', '').'|'.$cleanText);

        return $this->cacheDir.'/'.$hash.'.wav';
    }

    public function isAudioCached(string $text, ?string $voice = null, float $speed = 1.0): bool
    {
        return $this->manifestService->isAudioCached($text, $voice, $speed);
    }

    public function isProjectAudioComplete(Project $project, float $speed = 1.0): bool
    {
        return $this->manifestService->isProjectAudioComplete($project, $speed);
    }

    public function invalidateAudioCompleteCache(Project|string $project): void
    {
        $this->manifestService->invalidateAudioCompleteCache($project);
    }

    public function exportProjectToMp3(Project $project, float $speed = 1.0): string
    {
        return $this->manifestService->exportProjectToMp3($project, $speed);
    }

    public function prewarmManifest(array $manifest, float $speed = 1.0, ?callable $onProgress = null, ?Project $project = null): array
    {
        return $this->manifestService->prewarmManifest($manifest, $speed, $onProgress, $project);
    }

    protected function parseScreenplayText(
        string $text,
        array &$voiceMap,
        ?Project $project = null,
        bool $persistBibleChanges = false
    ): array {
        return $this->segmenter->parseScreenplayText($text, $voiceMap, $project, $persistBibleChanges);
    }

    protected function isCharacterLine(string $line): bool
    {
        return $this->segmenter->isCharacterLine($line);
    }

    protected function buildCharacterVoiceMap(Collection|array $characters, bool $persistBibleChanges = false): array
    {
        return $this->segmenter->buildCharacterVoiceMap($characters, $persistBibleChanges);
    }

    protected function resolveFallbackVoice(string $speaker): string
    {
        return $this->segmenter->resolveFallbackVoice($speaker);
    }

    protected function inferIsFemale(Character $char): bool
    {
        return $this->segmenter->inferIsFemale($char);
    }
}
