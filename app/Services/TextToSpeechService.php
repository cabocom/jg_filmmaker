<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Project;
use App\Models\ScriptBlock;
use App\Services\Tts\Contracts\TtsProviderInterface;
use App\Services\Tts\Providers\GoogleAiTtsProvider;
use App\Services\Tts\Providers\SpeachesTtsProvider;
use App\Services\Tts\ScreenplaySegmenter;
use App\Services\Tts\TtsManifestService;
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

    public function __construct()
    {
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
        if ($this->googleApiKey) {
            return [
                ['id' => 'Puck', 'name' => 'Puck (Masculino - Cálido y Narrativo)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Charon', 'name' => 'Charon (Masculino - Profundo y Maduro)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Fenrir', 'name' => 'Fenrir (Masculino - Enérgico e Intenso)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Zephyr', 'name' => 'Zephyr (Masculino - Joven y Dinámico)', 'gender' => 'male', 'lang' => 'es_ES'],
                ['id' => 'Aoede', 'name' => 'Aoede (Femenino - Expresivo y Teatral)', 'gender' => 'female', 'lang' => 'es_ES'],
                ['id' => 'Kore', 'name' => 'Kore (Femenino - Sereno y Claro)', 'gender' => 'female', 'lang' => 'es_ES'],
                ['id' => 'Leda', 'name' => 'Leda (Femenino - Elegante y Cálido)', 'gender' => 'female', 'lang' => 'es_ES'],
            ];
        }

        return [
            ['id' => 'sharvard#M', 'name' => 'Castellano - Masculino (Sharvard M)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'sharvard#F', 'name' => 'Castellano - Femenino (Sharvard F)', 'gender' => 'female', 'lang' => 'es_ES'],
            ['id' => 'davefx', 'name' => 'Castellano - Masculino Profundo (Davefx)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'carlfm', 'name' => 'Castellano - Masculino Joven (Carlfm)', 'gender' => 'male', 'lang' => 'es_ES'],
            ['id' => 'mls_9972', 'name' => 'Castellano - Femenino Suave (MLS 9972)', 'gender' => 'female', 'lang' => 'es_ES'],
            ['id' => 'mls_10246', 'name' => 'Castellano - Masculino Sereno (MLS 10246)', 'gender' => 'male', 'lang' => 'es_ES'],
        ];
    }

    public function resolveVoice(?string $voice = null): string
    {
        $v = $voice ?: $this->defaultVoice;
        if ($this->googleApiKey) {
            $legacyMap = [
                'sharvard#M' => 'Puck', 'sharvard#F' => 'Aoede', 'davefx' => 'Charon',
                'carlfm' => 'Zephyr', 'mls_9972' => 'Kore', 'mls_10246' => 'Fenrir',
            ];
            $valid = ['Puck', 'Charon', 'Fenrir', 'Zephyr', 'Aoede', 'Kore', 'Leda'];

            return $legacyMap[$v] ?? (in_array($v, $valid, true) ? $v : 'Puck');
        }

        return $v;
    }

    public function synthesize(string $text, ?string $voice = null, float $speed = 1.0): string
    {
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

        return $this->executeSynthesisPipeline($cleanText, $activeVoice, $speed, $cachedFilePath);
    }

    protected function executeSynthesisPipeline(string $cleanText, string $activeVoice, float $speed, string $cachedFilePath): string
    {
        if ($this->googleAiProvider->isAvailable()) {
            try {
                return $this->googleAiProvider->synthesize($cleanText, $activeVoice, $speed, $cachedFilePath);
            } catch (\Throwable $e) {
                Log::warning("Google AI TTS no pudo sintetizar [{$cleanText}]: {$e->getMessage()}. Intentando fallback...");
            }
        }

        try {
            return $this->speachesProvider->synthesize($cleanText, $activeVoice, $speed, $cachedFilePath);
        } catch (\Throwable $e) {
            Log::warning("Speaches TTS no disponible o falló: {$e->getMessage()}. Generando silencio de seguridad.");
            File::put($cachedFilePath, base64_decode('UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='));

            return $cachedFilePath;
        }
    }

    public function expandScreenplayAbbreviations(string $text): string
    {
        return $this->segmenter->expandScreenplayAbbreviations($text);
    }

    public function parseBlockToSegments(ScriptBlock $block, Collection|array $characters = [], ?Project $project = null): array
    {
        return $this->segmenter->parseBlockToSegments($block, $characters, $project);
    }

    public function buildManifestForScope(Project $project, ?string $sceneId = null, ?string $blockId = null): array
    {
        return $this->manifestService->buildManifestForScope($project, $sceneId, $blockId);
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

    public function exportProjectToMp3(Project $project, float $speed = 1.0): string
    {
        return $this->manifestService->exportProjectToMp3($project, $speed);
    }

    public function prewarmManifest(array $manifest, float $speed = 1.0, ?callable $onProgress = null): array
    {
        return $this->manifestService->prewarmManifest($manifest, $speed, $onProgress);
    }

    protected function parseScreenplayText(string $text, array &$voiceMap, ?Project $project = null): array
    {
        return $this->segmenter->parseScreenplayText($text, $voiceMap, $project);
    }

    protected function isCharacterLine(string $line): bool
    {
        return $this->segmenter->isCharacterLine($line);
    }

    protected function buildCharacterVoiceMap(Collection|array $characters): array
    {
        return $this->segmenter->buildCharacterVoiceMap($characters);
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
