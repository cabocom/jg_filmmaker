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

    public function __construct()
    {
        $this->speachesUrl = rtrim((string) config('services.speaches.url', 'http://speaches:8000'), '/');
        $this->model = (string) config('services.speaches.model', 'speaches-ai/piper-es_ES-sharvard-medium');
        $this->defaultVoice = (string) config('services.speaches.default_voice', 'sharvard#M');
        $this->timeout = (int) config('services.speaches.timeout', 30);
        $this->cacheDir = storage_path('app/private/tts_cache');

        if (! File::exists($this->cacheDir)) {
            File::makeDirectory($this->cacheDir, 0755, true, true);
        }
    }

    /**
     * Voces canónicas disponibles en castellano con el motor Speaches-AI (Piper TTS).
     *
     * @return array<int, array{id: string, name: string, gender: string, lang: string}>
     */
    public function getAvailableVoices(): array
    {
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
     * Resuelve la ruta del archivo de audio sintetizado. Si ya existe en caché,
     * se devuelve de inmediato sin realizar peticiones de red.
     */
    public function synthesize(string $text, ?string $voice = null, float $speed = 1.0): string
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            throw new RuntimeException('El texto a sintetizar no puede estar vacío.');
        }

        $activeVoice = $voice ?: $this->defaultVoice;
        $hash = hash('sha256', $this->model.'|'.$activeVoice.'|'.number_format($speed, 2, '.', '').'|'.$cleanText);
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

        // Si el bloque está en 'screenplay' (Guión Literario), aplicamos análisis dramático Fountain
        if ($block->stage === 'screenplay' && ! empty($block->screenplay_content)) {
            return $this->parseScreenplayText($block->screenplay_content, $characterVoiceMap);
        }

        // Si está en 'structured' o 'raw', se locuta el texto en prosa con la voz del Narrador
        $prose = ($block->stage === 'raw')
            ? ($block->raw_text ?: $block->structured_text)
            : ($block->structured_text ?: $block->raw_text);
        $cleanProse = trim((string) $prose);

        if ($cleanProse === '') {
            return [];
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
                    ]),
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
                        ]),
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
                    $cleanSpeaker = strtoupper(trim(preg_replace('/\s*\([^\)]+\)$/u', '', $currentSpeaker)));
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
        $femaleList = ['sharvard#F', 'mls_9972'];
        $maleList = ['sharvard#M', 'davefx', 'carlfm', 'mls_10246'];
        $femaleIdx = 0;
        $maleIdx = 0;

        foreach ($characters as $char) {
            $nameKey = strtoupper(trim((string) $char->name));
            $customAttrs = is_array($char->custom_attributes) ? $char->custom_attributes : [];
            $assignedVoice = $customAttrs['voice_id'] ?? null;

            if ($assignedVoice) {
                $map[$nameKey] = (string) $assignedVoice;

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
        $clean = strtoupper(trim($speaker));
        if (str_contains($clean, 'MUJER') || str_contains($clean, 'CHICA') || str_contains($clean, 'MADRE') || str_contains($clean, 'LUCÍA') || str_contains($clean, 'ANA')) {
            return 'sharvard#F';
        }

        return 'sharvard#M';
    }

    /**
     * Deduce si un personaje es femenino basándose en su psicología, bio o nombre.
     */
    protected function inferIsFemale(Character $char): bool
    {
        $text = mb_strtolower($char->name.' '.($char->bio ?? '').' '.($char->psychology ?? ''));

        if (preg_match('/\b(mujer|femenina|femenino|chica|actriz|hija|madre|hermana|abuela|señora|camarera|doctora)\b/u', $text)) {
            return true;
        }

        $name = mb_strtolower(trim($char->name));
        $femaleNames = ['ana', 'lucía', 'lucia', 'maría', 'maria', 'carmen', 'elena', 'laura', 'sofía', 'sofia', 'clara', 'marta', 'sara', 'irene', 'paula'];

        return in_array($name, $femaleNames, true);
    }
}
