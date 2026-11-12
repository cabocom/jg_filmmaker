<?php

namespace App\Services\JgAgente\Fallbacks;

use App\Services\JgAgente\TelemetryRecorder;

class StoryboardFallbackHandler
{
    /**
     * Emulación determinista de desglose de planos técnicos.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleShotBreakdown(
        array $payload,
        TelemetryRecorder $telemetry,
        callable $shotParser
    ): array {
        $screenplayText = trim((string) ($payload['screenplay_text'] ?? ''));

        if (empty($screenplayText)) {
            return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'shots', 'shots' => []]];
        }

        if (preg_match('/---\s*shot/i', $screenplayText)) {
            $shots = $shotParser($screenplayText);

            return [
                'success' => true,
                'output' => $screenplayText,
                'metadata' => ['stage' => 'shots', 'shots' => $shots],
            ];
        }

        $rawBlocks = array_values(array_filter(array_map('trim', explode("\n\n", $screenplayText))));
        $paragraphs = [];
        foreach ($rawBlocks as $b) {
            if (! preg_match('/^(INT|EXT|INT\/EXT|EST)[\.\s]/i', $b)) {
                $paragraphs[] = $b;
            }
        }

        if (empty($paragraphs)) {
            return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'shots', 'shots' => []]];
        }

        $shotUnits = $this->groupParagraphsIntoShotUnits($paragraphs);
        $annotated = [];
        $shots = [];
        $shotNum = 1;

        foreach ($shotUnits as $unit) {
            $type = $this->determineShotType($unit, $shotNum);
            $annotated[] = "--- shot ({$type}) ---\n{$unit}";
            $shots[] = [
                'shot_number' => $shotNum,
                'shot_type' => $type,
                'description' => $unit,
            ];
            $shotNum++;
        }

        $annotatedText = implode("\n\n", $annotated);

        $telemetry->setLastTelemetry([
            'tool' => 'shot_breakdown_detector',
            'stage' => 'shots',
            'provider' => 'deterministic_engine',
            'model' => 'rule-based-nlp',
            'executed_at' => now()->toIso8601String(),
            'duration_ms' => 6,
            'status' => 'SUCCESS',
            'raw_response' => $annotatedText,
            'output' => $annotatedText,
            'characters_context' => [],
            'location_context' => null,
            'user_locks' => [],
            'steps' => [
                ['number' => 1, 'title' => 'Análisis de Escena y Ritmo', 'status' => 'completed', 'detail' => 'Segmentación de bloques.'],
                ['number' => 2, 'title' => 'Asignación de Cobertura de Cámara', 'status' => 'completed', 'detail' => count($shots).' planos técnicos asignados.'],
                ['number' => 3, 'title' => 'Delimitación Canónica', 'status' => 'completed', 'detail' => 'Sintaxis --- shot N (tipo) --- inyectada.'],
            ],
        ]);

        return [
            'success' => true,
            'output' => $annotatedText,
            'metadata' => [
                'stage' => 'shots',
                'shots' => $shots,
                'total_shots' => count($shots),
            ],
        ];
    }

    /**
     * Agrupa párrafos en unidades dramáticas coherentes.
     *
     * @param  list<string>  $paragraphs
     * @return list<string>
     */
    protected function groupParagraphsIntoShotUnits(array $paragraphs): array
    {
        $shotUnits = [];
        $currentUnit = [];

        foreach ($paragraphs as $p) {
            $isCharacter = preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\(\)\.-]{2,30}$/', $p) && ! preg_match('/[a-z]/', $p);
            $isCameraCue = preg_match('/^(detalle|primer plano|plano general|inserto|plano medio|corte a|insert)\b/i', $p);

            if ($isCharacter || $isCameraCue) {
                if (! empty($currentUnit)) {
                    $shotUnits[] = implode("\n", $currentUnit);
                    $currentUnit = [];
                }
                $currentUnit[] = $p;
                if ($isCameraCue) {
                    $shotUnits[] = implode("\n", $currentUnit);
                    $currentUnit = [];
                }
            } else {
                $currentUnit[] = $p;
                if (count($currentUnit) >= 2) {
                    $shotUnits[] = implode("\n", $currentUnit);
                    $currentUnit = [];
                }
            }
        }

        if (! empty($currentUnit)) {
            $shotUnits[] = implode("\n", $currentUnit);
        }

        return ! empty($shotUnits) ? $shotUnits : $paragraphs;
    }

    /**
     * Determina el tipo de plano según semántica del texto.
     */
    protected function determineShotType(string $unit, int $shotNum): string
    {
        $firstLine = strtok($unit, "\n");
        $isDialogue = preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\(\)\.-]{2,30}$/', (string) $firstLine) && ! preg_match('/[a-z]/', (string) $firstLine);

        if ($isDialogue) {
            return ($shotNum % 2 === 0) ? 'PP' : 'PM';
        }
        if (preg_match('/(detalle|inserto|botas|reloj|llaves|objeto)/i', $unit)) {
            return 'PD';
        }
        if (preg_match('/(mira|ojos|sonríe|llora|respira|toma|coge|sostiene|muestra|mano|rostro|ambientador)/i', $unit)) {
            return 'PP';
        }
        if (preg_match('/(entra|sale|camina|llega|cruza|corre|paisaje|calle|habitación|salón|ciudad)/i', $unit)) {
            return ($shotNum === 1) ? 'PG' : 'PM';
        }

        return 'PM';
    }

    /**
     * Fallback determinista para generación de viñeta storyboard.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleStoryboardGeneration(array $payload): array
    {
        return [
            'success' => true,
            'output' => 'Viñeta generada determinísticamente (modo offline).',
            'metadata' => [
                'image_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                'mime_type' => 'image/png',
                'style' => $payload['style'] ?? 'grayscale_sketch',
                'final_prompt' => $payload['prompt'] ?? '',
                'aspect_ratio' => $payload['aspect_ratio'] ?? '16:9',
                'source' => 'deterministic_fallback',
            ],
        ];
    }
}
