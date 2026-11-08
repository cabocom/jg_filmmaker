<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
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

    public function __construct()
    {
        $this->speachesUrl = rtrim((string) config('services.speaches.url', 'http://speaches:8000'), '/');
        $this->model = (string) config('services.speaches.model', 'speaches-ai/piper-es_ES-sharvard-medium');
        $this->googleApiKey = config('services.google_ai.api_key') ?: null;
        $this->googleAiBaseUrl = rtrim((string) config('services.google_ai.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->googleAiTtsModel = (string) config('services.google_ai.tts_model', 'gemini-3.8-flash-tts');
        $this->defaultVoice = $this->googleApiKey ? 'Puck' : (string) config('services.speaches.default_voice', 'sharvard#M');
        $this->timeout = (int) config('services.speaches.timeout', 30);
        $this->cacheDir = storage_path('app/private/tts_cache');

        if (! File::exists($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0775, true, true);
        }
    }

    public function getDefaultVoice(): string
    {
        return $this->defaultVoice;
    }

    /**
     * Voces canónicas disponibles para la lectura dramática.
     * Si está configurado Google AI, se proporcionan las voces neurales de estudio de Gemini.
     *
     * @return array<int, array{id: string, name: string, gender: string, lang: string}>
     */
    public function getAvailableVoices(): array
    {
        if ($this->googleApiKey) {
            return [
                [
                    'id' => 'Puck',
                    'name' => 'Puck (Masculino - Cálido y Narrativo)',
                    'gender' => 'male',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Charon',
                    'name' => 'Charon (Masculino - Profundo y Maduro)',
                    'gender' => 'male',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Fenrir',
                    'name' => 'Fenrir (Masculino - Enérgico e Intenso)',
                    'gender' => 'male',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Zephyr',
                    'name' => 'Zephyr (Masculino - Joven y Dinámico)',
                    'gender' => 'male',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Aoede',
                    'name' => 'Aoede (Femenino - Expresivo y Teatral)',
                    'gender' => 'female',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Kore',
                    'name' => 'Kore (Femenino - Sereno y Claro)',
                    'gender' => 'female',
                    'lang' => 'es_ES',
                ],
                [
                    'id' => 'Leda',
                    'name' => 'Leda (Femenino - Elegante y Cálido)',
                    'gender' => 'female',
                    'lang' => 'es_ES',
                ],
            ];
        }

        return [
            [
                'id' => 'sharvard#M',
                'name' => 'Castellano - Masculino (Sharvard M)',
                'gender' => 'male',
                'lang' => 'es_ES',
            ],
            [
                'id' => 'sharvard#F',
                'name' => 'Castellano - Femenino (Sharvard F)',
                'gender' => 'female',
                'lang' => 'es_ES',
            ],
            [
                'id' => 'davefx',
                'name' => 'Castellano - Masculino Profundo (Davefx)',
                'gender' => 'male',
                'lang' => 'es_ES',
            ],
            [
                'id' => 'carlfm',
                'name' => 'Castellano - Masculino Joven (Carlfm)',
                'gender' => 'male',
                'lang' => 'es_ES',
            ],
            [
                'id' => 'mls_9972',
                'name' => 'Castellano - Femenino Suave (MLS 9972)',
                'gender' => 'female',
                'lang' => 'es_ES',
            ],
            [
                'id' => 'mls_10246',
                'name' => 'Castellano - Masculino Sereno (MLS 10246)',
                'gender' => 'male',
                'lang' => 'es_ES',
            ],
        ];
    }

    /**
     * Resuelve y normaliza el identificador de voz, mapeando automáticamente
     * voces legadas de Speaches a las voces de estudio de Google AI si procede.
     */
    public function resolveVoice(?string $voice = null): string
    {
        $v = $voice ?: $this->defaultVoice;

        if ($this->googleApiKey) {
            $legacyMap = [
                'sharvard#M' => 'Puck',
                'sharvard#F' => 'Aoede',
                'davefx' => 'Charon',
                'carlfm' => 'Zephyr',
                'mls_9972' => 'Kore',
                'mls_10246' => 'Fenrir',
            ];
            $valid = ['Puck', 'Charon', 'Fenrir', 'Zephyr', 'Aoede', 'Kore', 'Leda'];

            return $legacyMap[$v] ?? (in_array($v, $valid, true) ? $v : 'Puck');
        }

        return $v;
    }

    /**
     * Resuelve la ruta del archivo de audio sintetizado. Si ya existe en caché,
     * se devuelve de inmediato sin realizar peticiones de red.
     */
    public function synthesize(string $text, ?string $voice = null, float $speed = 1.0): string
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            throw new RuntimeException('El texto a sintetizar no puede estar vacío.');
        }

        $activeVoice = $this->resolveVoice($voice);
        $providerKey = $this->googleApiKey ? 'google_ai_'.$this->googleAiTtsModel : $this->model;
        $hash = hash('sha256', $providerKey.'|'.$activeVoice.'|'.number_format($speed, 2, '.', '').'|'.$cleanText);
        $cachedFilePath = $this->cacheDir.'/'.$hash.'.wav';

        // Caché hit
        if (File::exists($cachedFilePath) && File::size($cachedFilePath) > 0) {
            return $cachedFilePath;
        }

        // Si estamos en testing y no hay mock HTTP activo, generar un archivo WAV dummy
        if (app()->environment('testing') && ! Http::hasMacro('tts_testing_real')) {
            $dummyWav = base64_decode('UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=');
            File::put($cachedFilePath, $dummyWav);

            return $cachedFilePath;
        }

        if ($this->googleApiKey) {
            return $this->synthesizeWithGoogleAi($cleanText, $activeVoice, $cachedFilePath);
        }

        return $this->synthesizeWithSpeaches($cleanText, $activeVoice, $speed, $cachedFilePath);
    }

    /**
     * Síntesis de voz neural con Google AI Studio (Gemini TTS).
     */
    protected function synthesizeWithGoogleAi(string $cleanText, string $voice, string $cachedFilePath): string
    {
        try {
            $url = "{$this->googleAiBaseUrl}/models/{$this->googleAiTtsModel}:generateContent?key={$this->googleApiKey}";

            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $cleanText],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => [
                            'voiceConfig' => [
                                'prebuiltVoiceConfig' => [
                                    'voiceName' => $voice,
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Fallo en síntesis TTS con Google AI', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'voice' => $voice,
                ]);

                throw new RuntimeException('Error al contactar con Google AI TTS: '.$response->status());
            }

            $parts = $response->json('candidates.0.content.parts') ?? [];
            $base64Data = null;
            foreach ($parts as $part) {
                if (! empty($part['inlineData']['data'])) {
                    $base64Data = $part['inlineData']['data'];
                    break;
                }
            }

            if (! $base64Data) {
                throw new RuntimeException('Google AI TTS no devolvió datos de audio válidos.');
            }

            $wavBytes = base64_decode($base64Data);
            File::put($cachedFilePath, $wavBytes);

            return $cachedFilePath;
        } catch (\Throwable $e) {
            Log::error('Excepción al sintetizar voz vía Google AI', [
                'message' => $e->getMessage(),
                'voice' => $voice,
            ]);

            throw new RuntimeException('No se pudo generar la locución con Google AI: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Síntesis de voz con Speaches-AI (Piper local).
     */
    protected function synthesizeWithSpeaches(string $cleanText, string $activeVoice, float $speed, string $cachedFilePath): string
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
                    'voice' => $activeVoice,
                    'response_format' => 'wav',
                    'speed' => $speed,
                ]);

            if (! $response->successful()) {
                Log::error('Fallo en síntesis TTS con Speaches', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'text_snippet' => mb_substr($cleanText, 0, 80),
                    'voice' => $activeVoice,
                ]);

                throw new RuntimeException('Error al contactar con el servicio de voz Speaches: '.$response->status());
            }

            File::put($cachedFilePath, $response->body());

            return $cachedFilePath;
        } catch (\Throwable $e) {
            Log::error('Excepción al sintetizar voz vía Speaches', [
                'message' => $e->getMessage(),
                'voice' => $activeVoice,
                'endpoint' => $this->speachesUrl,
            ]);

            throw new RuntimeException('No se pudo generar la locución: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Parsea un bloque de guión literario (Screenplay) o texto libre en segmentos
     * ordenados de locución, diferenciando al narrador y a los personajes dialogantes.
     *
     * @param  Collection<int, Character>|array<int, Character>  $characters
     * @return array<int, array{type: string, speaker: string, voice: string, text: string}>
     */
    public function parseBlockToSegments(ScriptBlock $block, Collection|array $characters = []): array
    {
        $characterVoiceMap = $this->buildCharacterVoiceMap($characters);

        // Si el bloque está en 'screenplay' o 'shots', aplicamos análisis dramático Fountain sobre el guión literario
        if (in_array($block->stage, ['screenplay', 'shots'], true) && ! empty($block->screenplay_content)) {
            return $this->parseScreenplayText($block->screenplay_content, $characterVoiceMap);
        }

        // Si está en 'structured' o 'raw', comprobamos si contiene formato dramático con personajes
        $prose = ($block->stage === 'raw')
            ? ($block->raw_text ?: $block->structured_text)
            : ($block->structured_text ?: $block->raw_text);
        $cleanProse = trim((string) $prose);

        if ($cleanProse === '') {
            return [];
        }

        $candidateSegments = $this->parseScreenplayText($cleanProse, $characterVoiceMap);
        $hasDialogue = collect($candidateSegments)->contains('type', 'dialogue');

        if ($hasDialogue) {
            return $candidateSegments;
        }

        return [
            [
                'type' => 'narrator',
                'speaker' => 'NARRADOR',
                'voice' => $this->defaultVoice,
                'text' => $cleanProse,
            ],
        ];
    }

    /**
     * Construye el manifiesto completo de reproducción para un bloque, una escena o todo el proyecto.
     *
     * @return array<int, array{block_id: string, scene_id: string, type: string, speaker: string, voice: string, text: string, stream_url: string}>
     */
    public function buildManifestForScope(Project $project, ?string $sceneId = null, ?string $blockId = null): array
    {
        $characters = $project->characters()->get();
        $manifest = [];

        if ($blockId) {
            $block = ScriptBlock::where('id', $blockId)
                ->whereHas('scene', fn ($q) => $q->where('project_id', $project->id))
                ->firstOrFail();

            $segments = $this->parseBlockToSegments($block, $characters);
            foreach ($segments as $idx => $segment) {
                $manifest[] = [
                    'block_id' => $block->id,
                    'scene_id' => $block->scene_id,
                    'type' => $segment['type'],
                    'speaker' => $segment['speaker'],
                    'voice' => $segment['voice'],
                    'text' => $segment['text'],
                    'stream_url' => route('projects.tts.stream', [
                        'project' => $project->id,
                        'block_id' => $block->id,
                        'segment_index' => $idx,
                    ], false),
                ];
            }

            return $manifest;
        }

        $scenesQuery = $project->scenes()->orderBy('scene_order');
        if ($sceneId) {
            $scenesQuery->where('id', $sceneId);
        }

        /** @var Collection<int, Scene> $scenes */
        $scenes = $scenesQuery->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])->get();

        foreach ($scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                $segments = $this->parseBlockToSegments($block, $characters);
                foreach ($segments as $idx => $segment) {
                    $manifest[] = [
                        'block_id' => $block->id,
                        'scene_id' => $scene->id,
                        'type' => $segment['type'],
                        'speaker' => $segment['speaker'],
                        'voice' => $segment['voice'],
                        'text' => $segment['text'],
                        'stream_url' => route('projects.tts.stream', [
                            'project' => $project->id,
                            'block_id' => $block->id,
                            'segment_index' => $idx,
                        ], false),
                    ];
                }
            }
        }

        return $manifest;
    }

    /**
     * Parsea texto en formato de guión literario (Fountain/Canónico) aislando
     * acotaciones, sluglines y diálogos directos por personaje.
     *
     * @param  array<string, string>  $voiceMap
     * @return array<int, array{type: string, speaker: string, voice: string, text: string}>
     */
    protected function parseScreenplayText(string $text, array $voiceMap): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = explode("\n", $normalized);

        $segments = [];
        $currentSpeaker = null;
        $currentDialogueLines = [];
        $currentActionLines = [];

        $flushAction = function () use (&$segments, &$currentActionLines) {
            if (! empty($currentActionLines)) {
                $text = trim(implode("\n", $currentActionLines));
                if ($text !== '') {
                    $segments[] = [
                        'type' => 'narrator',
                        'speaker' => 'NARRADOR',
                        'voice' => $this->defaultVoice,
                        'text' => $text,
                    ];
                }
                $currentActionLines = [];
            }
        };

        $flushDialogue = function () use (&$segments, &$currentSpeaker, &$currentDialogueLines, $voiceMap) {
            if ($currentSpeaker !== null && ! empty($currentDialogueLines)) {
                $text = trim(implode(' ', $currentDialogueLines));
                if ($text !== '') {
                    $cleanSpeaker = rtrim(strtoupper(trim(preg_replace('/\s*\([^\)]+\)$/u', '', $currentSpeaker))), '.');
                    $voice = $voiceMap[$cleanSpeaker] ?? $this->resolveFallbackVoice($cleanSpeaker);

                    $segments[] = [
                        'type' => 'dialogue',
                        'speaker' => $cleanSpeaker,
                        'voice' => $voice,
                        'text' => $text,
                    ];
                }
                $currentSpeaker = null;
                $currentDialogueLines = [];
            }
        };

        $inDialogue = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($inDialogue) {
                    $flushDialogue();
                    $inDialogue = false;
                }

                continue;
            }

            // Detección de diálogo inline: "PERSONAJE (acotación): texto" o "PERSONAJE: texto"
            if (! $inDialogue && preg_match('/^([A-ZÁÉÍÓÚÑ0-9\s\.\-\'\’]+?)(?:\s*\([^\)]+\))?:\s*(.+)$/u', $line, $inlineMatch)) {
                $possibleSpeaker = trim($inlineMatch[1]);
                if ($this->isCharacterLine($possibleSpeaker)) {
                    $flushAction();
                    $currentSpeaker = $possibleSpeaker;
                    $currentDialogueLines[] = trim($inlineMatch[2]);
                    $flushDialogue();

                    continue;
                }
            }

            // Detección de personaje en mayúsculas
            if ($this->isCharacterLine($line) && ! $inDialogue) {
                $flushAction();
                $currentSpeaker = $line;
                $inDialogue = true;

                continue;
            }

            // Si estamos en diálogo
            if ($inDialogue) {
                // Las acotaciones tipo (con desdén) se omiten en la lectura del actor
                if (str_starts_with($line, '(') && str_ends_with($line, ')')) {
                    continue;
                }
                $currentDialogueLines[] = $line;

                continue;
            }

            // Acción descriptiva o slugline (leído por el narrador)
            $currentActionLines[] = $line;
        }

        $flushDialogue();
        $flushAction();

        return $segments;
    }

    /**
     * Determina si una línea corresponde a un nombre de personaje dialogante en guión.
     */
    protected function isCharacterLine(string $line): bool
    {
        $clean = trim($line);
        if ($clean === '' || mb_strlen($clean) > 45) {
            return false;
        }

        if (preg_match('/^(INT\.|EXT\.|INT\/EXT\.|I\/E\.|EST\.)\s+/iu', $clean)) {
            return false;
        }

        if (preg_match('/^(CORTE A:|DISUELVE A:|FUNDIDO A NEGRO:|FADE OUT\.|FADE IN:|CUT TO:|DISSOLVE TO:)$/iu', $clean)) {
            return false;
        }

        $withoutParenthetical = preg_replace('/\s*\([^\)]+\)$/u', '', $clean);
        $withoutParenthetical = trim($withoutParenthetical);

        if ($withoutParenthetical === '') {
            return false;
        }

        return preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\.\-\'\’]+$/u', $withoutParenthetical)
            && preg_match('/[A-ZÁÉÍÓÚÑ]/u', $withoutParenthetical)
            && ! str_ends_with($withoutParenthetical, '.')
            && ! str_ends_with($withoutParenthetical, ',')
            && ! str_ends_with($withoutParenthetical, ':');
    }

    /**
     * Mapea personajes a sus voces asignadas.
     *
     * @param  Collection<int, Character>|array<int, Character>  $characters
     * @return array<string, string>
     */
    protected function buildCharacterVoiceMap(Collection|array $characters): array
    {
        $map = [];
        $femaleList = $this->googleApiKey ? ['Aoede', 'Kore', 'Leda'] : ['sharvard#F', 'mls_9972'];
        $maleList = $this->googleApiKey ? ['Puck', 'Charon', 'Fenrir', 'Zephyr'] : ['sharvard#M', 'davefx', 'carlfm', 'mls_10246'];
        $femaleIdx = 0;
        $maleIdx = 0;

        foreach ($characters as $char) {
            $nameKey = rtrim(strtoupper(trim((string) $char->name)), '.');
            $customAttrs = is_array($char->custom_attributes) ? $char->custom_attributes : [];
            $assignedVoice = $customAttrs['voice_id'] ?? null;

            if ($assignedVoice) {
                $map[$nameKey] = $this->resolveVoice((string) $assignedVoice);

                continue;
            }

            // Inferencia heurística por nombre o género si está especificado
            $isFemale = $this->inferIsFemale($char);
            if ($isFemale) {
                $map[$nameKey] = $femaleList[$femaleIdx % count($femaleList)];
                $femaleIdx++;
            } else {
                $map[$nameKey] = $maleList[$maleIdx % count($maleList)];
                $maleIdx++;
            }
        }

        return $map;
    }

    /**
     * Resuelve una voz alternativa determinista si el personaje no está en la biblia.
     */
    protected function resolveFallbackVoice(string $speaker): string
    {
        $clean = rtrim(strtoupper(trim($speaker)), '.');
        $isFemale = str_contains($clean, 'MUJER')
            || str_contains($clean, 'CHICA')
            || str_contains($clean, 'MADRE')
            || str_contains($clean, 'LUCÍA')
            || str_contains($clean, 'ANA')
            || str_contains($clean, 'EDURNE')
            || str_contains($clean, 'MARIA')
            || str_contains($clean, 'MARÍA')
            || str_contains($clean, 'NEKANE')
            || str_contains($clean, 'VOZ IA')
            || str_contains($clean, 'IA');

        if ($this->googleApiKey) {
            return $isFemale ? 'Aoede' : 'Charon';
        }

        return $isFemale ? 'sharvard#F' : 'sharvard#M';
    }

    /**
     * Deduce si un personaje es femenino basándose en su psicología, bio o nombre.
     */
    protected function inferIsFemale(Character $char): bool
    {
        $customAttrs = is_array($char->custom_attributes) ? $char->custom_attributes : [];
        if (! empty($customAttrs['gender'])) {
            return in_array(mb_strtolower((string) $customAttrs['gender']), ['female', 'femenino', 'mujer'], true);
        }

        $text = mb_strtolower($char->name.' '.($char->bio ?? '').' '.($char->psychology ?? ''));

        if (preg_match('/\b(mujer|femenina|femenino|chica|actriz|hija|madre|hermana|abuela|señora|camarera|doctora|muchacha|chavala|locutora|protagonista femenina)\b/u', $text)) {
            return true;
        }

        $name = rtrim(mb_strtolower(trim($char->name)), '.');
        $femaleNames = [
            'ana', 'lucía', 'lucia', 'maría', 'maria', 'carmen', 'elena', 'laura', 'sofía', 'sofia',
            'clara', 'marta', 'sara', 'irene', 'paula', 'edurne', 'nekane', 'ainhoa', 'itziar',
            'arantxa', 'maite', 'leire', 'nerea', 'begoña', 'idoia', 'amaia', 'miren', 'almudena',
            'inés', 'ines', 'rocío', 'rocio', 'raquel', 'beatriz', 'isabel', 'teresa', 'eva',
            'silvia', 'cristina', 'patricia', 'mónica', 'monica', 'alicia', 'rosa', 'pilar',
            'mercedes', 'lourdes', 'victoria', 'blanca', 'esther', 'ester', 'julia', 'natalia',
            'lorena', 'alba', 'claudia', 'andrea', 'celia', 'marina', 'triana', 'voz ia', 'ia',
        ];

        if (in_array($name, $femaleNames, true)) {
            return true;
        }

        if (preg_match('/(a|ne|z|is|ina|esa|isa|triz)$/u', $name) && ! in_array($name, ['borja', 'luca', 'joshua', 'andrea'], true)) {
            return true;
        }

        return false;
    }
}
