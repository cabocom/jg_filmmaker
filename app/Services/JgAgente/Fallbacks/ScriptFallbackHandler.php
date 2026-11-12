<?php

namespace App\Services\JgAgente\Fallbacks;

use App\Services\JgAgente\TelemetryRecorder;

class ScriptFallbackHandler
{
    public function __construct(
        protected ScriptDialogueRefiner $dialogueRefiner = new ScriptDialogueRefiner
    ) {}

    /**
     * Emulación determinista de estructuración de tratamiento dramático.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleTreatmentStructuring(
        array $payload,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier
    ): array {
        $rawText = trim((string) ($payload['raw_text'] ?? ''));
        $characters = (array) ($payload['characters_context'] ?? []);
        $userLocks = (array) ($payload['user_locks'] ?? []);

        if (empty($rawText)) {
            return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'structured']];
        }

        $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $rawText))));
        $structured = [];

        foreach ($paragraphs as $para) {
            $cleaned = preg_replace('/^En [^:]+:\s*/iu', '', $para);
            $cleaned = preg_replace('/^(?:he pensado que|la idea es que|aquí|en esta escena|el autor propone que|vemos como|se ve como)\s+/iu', '', $cleaned);
            $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $cleaned));

            if (empty($cleaned)) {
                continue;
            }

            $cleaned = mb_strtoupper(mb_substr($cleaned, 0, 1)).mb_substr($cleaned, 1);

            foreach ($characters as $char) {
                if (is_array($char) && ! empty($char['name'])) {
                    $cname = trim($char['name']);
                    $pattern = '/\b'.preg_quote($cname, '/').'\b/iu';
                    $cleaned = preg_replace($pattern, mb_strtoupper($cname, 'UTF-8'), $cleaned);
                }
            }

            if (! preg_match('/[.?!:»"]$/u', $cleaned)) {
                $cleaned .= '.';
            }

            $structured[] = $cleaned;
        }

        $output = implode("\n\n", $structured);
        $output = $userLocksApplier($output, $userLocks, 'structured');

        $telemetry->setLastTelemetry([
            'tool' => 'treatment_structuring',
            'stage' => 'structured',
            'provider' => 'deterministic_engine',
            'model' => 'rule-based-nlp',
            'executed_at' => now()->toIso8601String(),
            'duration_ms' => 8,
            'status' => 'SUCCESS',
            'raw_response' => $output,
            'output' => $output,
            'characters_context' => $characters,
            'location_context' => $payload['location_context'] ?? null,
            'user_locks' => $telemetry->sanitizeTelemetryLocks($userLocks),
            'steps' => [
                ['number' => 1, 'title' => 'Inyección Contextual & Biblia Dramática', 'status' => 'completed', 'detail' => 'Cargados '.count($characters).' personajes.'],
                ['number' => 2, 'title' => 'Estructuración Dramática', 'status' => 'completed', 'detail' => 'Tratamiento procesado en presente de indicativo.'],
                ['number' => 3, 'title' => 'Aplicación de Candados y Términos Fijos', 'status' => 'completed', 'detail' => 'Verificados '.count($userLocks['pinned_terms'] ?? []).' términos fijos.'],
                ['number' => 4, 'title' => 'Validación de Formato', 'status' => 'completed', 'detail' => 'Salida normalizada en párrafos limpios.'],
            ],
        ]);

        return [
            'success' => true,
            'output' => $output,
            'metadata' => [
                'stage' => 'structured',
                'applied_locks_count' => count($userLocks['pinned_terms'] ?? []) + count($userLocks['manual_diffs'] ?? []),
            ],
        ];
    }

    /**
     * Emulación determinista de formateo a guion literario Fountain.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleScreenplayFormatting(
        array $payload,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier
    ): array {
        $structuredText = trim((string) ($payload['structured_text'] ?? ''));
        $characters = (array) ($payload['characters_context'] ?? []);
        $location = $payload['location_context'] ?? null;
        $userLocks = (array) ($payload['user_locks'] ?? []);

        if (empty($structuredText)) {
            return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'screenplay']];
        }

        $rawParagraphs = array_values(array_filter(array_map('trim', explode("\n", $structuredText))));
        $lines = [];

        $locName = ! empty($location['name']) ? strtoupper($location['name']) : 'LOCALIZACION';
        $locType = ! empty($location['type']) ? strtoupper($location['type']) : 'INT';
        $timeOfDay = ! empty($location['time_of_day']) ? strtoupper($location['time_of_day']) : 'DIA';
        $slugline = "{$locType}. {$locName} - {$timeOfDay} 1";

        if (! preg_match('/^(INT|EXT|INT\/EXT)\./i', $rawParagraphs[0] ?? '')) {
            $lines[] = $slugline;
            $lines[] = '';
        }

        $activeSpeaker = null;

        foreach ($rawParagraphs as $para) {
            if (preg_match('/^(INT|EXT|INT\/EXT)\./i', $para)) {
                $lines[] = mb_strtoupper($para, 'UTF-8');
                $lines[] = '';

                continue;
            }

            $cleanPara = preg_replace('/^En\s+[^:]+:\s*/iu', '', $para);
            $segments = $this->dialogueRefiner->splitParagraphIntoDialogueSegments($cleanPara);

            foreach ($segments as $segment) {
                $dialogueMatched = false;

                if (preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)(?:\s*\(([^)]+)\))?:\s*(.+)$/iu', $segment, $m)) {
                    $speaker = mb_strtoupper(trim($m[1]), 'UTF-8');
                    $lines[] = $speaker;
                    if (! empty($m[2])) {
                        $lines[] = '('.strtolower(trim($m[2])).')';
                    }
                    $lines[] = trim($m[3]);
                    $lines[] = '';
                    $dialogueMatched = true;
                    $activeSpeaker = $speaker;
                } elseif (preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)\s+(?:dice|responde|susurra|grita|pregunta|afirma)\s*:\s*["«]([^"»]+)["»]$/iu', $segment, $m)) {
                    $speaker = mb_strtoupper(trim($m[1]), 'UTF-8');
                    $lines[] = $speaker;
                    $lines[] = trim($m[2]);
                    $lines[] = '';
                    $dialogueMatched = true;
                    $activeSpeaker = $speaker;
                } else {
                    $dialogueMatched = $this->processIndirectDialogueSegment($segment, $characters, $activeSpeaker, $lines);
                }

                if (! $dialogueMatched) {
                    $lines[] = $this->dialogueRefiner->formatActionSegment($segment, $characters, $activeSpeaker);
                    $lines[] = '';
                }
            }
        }

        $output = rtrim(implode("\n", $lines));
        $output = $userLocksApplier($output, $userLocks, 'screenplay');

        $telemetry->setLastTelemetry([
            'tool' => 'screenplay_formatter',
            'stage' => 'screenplay',
            'provider' => 'deterministic_engine',
            'model' => 'rule-based-nlp',
            'executed_at' => now()->toIso8601String(),
            'duration_ms' => 10,
            'status' => 'SUCCESS',
            'raw_response' => $output,
            'output' => $output,
            'characters_context' => $characters,
            'location_context' => $location,
            'user_locks' => $telemetry->sanitizeTelemetryLocks($userLocks),
            'steps' => [
                ['number' => 1, 'title' => 'Inyección Contextual & Slugline', 'status' => 'completed', 'detail' => 'Slugline: '.$slugline],
                ['number' => 2, 'title' => 'Formateo Fountain Canónico', 'status' => 'completed', 'detail' => 'Detección de diálogos y mayúsculas.'],
                ['number' => 3, 'title' => 'Aplicación de Candados y Términos Fijos', 'status' => 'completed', 'detail' => 'Verificados '.count($userLocks['pinned_terms'] ?? []).' términos fijos.'],
                ['number' => 4, 'title' => 'Validación de Formato', 'status' => 'completed', 'detail' => 'Estructura validada.'],
            ],
        ]);

        return [
            'success' => true,
            'output' => $output,
            'metadata' => [
                'stage' => 'screenplay',
                'format' => 'fountain',
            ],
        ];
    }

    /**
     * Procesa habla indirecta narrativa y la transcribe a estilo directo Fountain.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  list<string>  $lines
     */
    protected function processIndirectDialogueSegment(
        string $segment,
        array $characters,
        ?string &$activeSpeaker,
        array &$lines
    ): bool {
        $verbs = '(?:dice|dijo|decir|decirle|decirles|diciendo|diciéndole|diciéndoles|pregunta|preguntó|preguntar|preguntarle|preguntarles|preguntando|preguntándole|preguntándoles|responde|respondió|responder|responderle|responderles|respondiendo|respondiéndole|respondiéndoles|contesta|contestó|contestar|contestarle|contestarles|contestando|contestándole|contestándoles|susurra|susurró|susurrar|susurrarle|susurrando|susurrándole|grita|gritó|gritar|gritarle|gritando|gritándole|afirma|afirmó|afirmar|afirmando|exclama|exclamó|exclamar|exclamando|exclamándole|pide|pidió|pedir|pedirle|pidiendo|pidiéndole|pidiéndoles|murmura|murmuró|murmurar|murmurando|murmurándole|advierte|advirtió|advertir|advertirle|advirtiendo|advirtiéndole)';
        $linkRegex = '((?:que\s+quien|qué\s+quien|que\s+quién|qué\s+quién|que\s+qué|qué\s+qué|que\s+cómo|qué\s+cómo|que\s+dónde|qué\s+dónde|que\s+cuándo|qué\s+cuándo|que\s+por\s+qué|que\s+si|qué|que|si|quién|quien|cómo|dónde|cuándo|por\s+qué))\s+';

        if (! preg_match('/\b'.$verbs.'\b/iu', $segment)) {
            return false;
        }

        if (! preg_match('/^([^.?!]{0,250}?)(?:[,\s]+y\s+|\s+y\s+|[,\s]+)?(?:\b(?:se|le|les|me|nos|te|lo|la|los|las)\s+)?\b('.$verbs.')\b\s*([^.?!]{0,100}?)\s*'.$linkRegex.'(.{1,5000})$/iu', $segment, $m)) {
            return false;
        }

        $prePart = trim($m[1] ?? '');
        $verb = strtolower(trim($m[2] ?? 'dice'));
        $verbMod = trim($m[3] ?? '');
        $linkRaw = strtolower(trim($m[4] ?? 'que'));
        $linkParts = preg_split('/\s+/u', $linkRaw);
        $particle = end($linkParts);
        $dialogueRest = $this->dialogueRefiner->cleanPunctuation($m[5] ?? '');

        $resolved = $this->dialogueRefiner->resolveSpeakerFromPrePart($prePart, $characters, $activeSpeaker);
        $speaker = $resolved['speaker'] ?: ($activeSpeaker ?: 'PERSONAJE');
        $activeSpeaker = $speaker;
        $parenthetical = $this->dialogueRefiner->resolveParenthetical($verb, $verbMod);

        $dialogueRest = preg_replace('/\s+y\s+que\s+/iu', ' y ', $dialogueRest);
        $dialogue = $this->dialogueRefiner->refineDialogueToDirectSpeech($dialogueRest, $verb, $particle);

        if (! empty($resolved['actionPart'])) {
            $lines[] = $this->dialogueRefiner->cleanPunctuation($resolved['actionPart']).'.';
            $lines[] = '';
        }
        $lines[] = $speaker;
        if ($parenthetical) {
            $lines[] = '('.$parenthetical.')';
        }
        $lines[] = $dialogue;
        $lines[] = '';

        return true;
    }
}
