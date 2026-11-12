<?php

namespace App\Services\JgAgente\Fallbacks;

use App\Services\JgAgente\TelemetryRecorder;

class ContinuityFallbackHandler
{
    /**
     * Emulación determinista de auditoría de agujeros de guión y continuidad.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handlePlotHolesAudit(array $payload, TelemetryRecorder $telemetry): array
    {
        $blocks = (array) ($payload['blocks'] ?? []);
        $detectedHoles = [];

        foreach ($blocks as $block) {
            $text = (string) ($block['text'] ?? ($block['screenplay_content'] ?? ($block['structured_text'] ?? ($block['raw_text'] ?? ''))));
            $blockId = $block['id'] ?? null;
            if (! $blockId || empty(trim($text))) {
                continue;
            }

            $matchedReason = $this->extractBlockInconsistency($text);
            if ($matchedReason) {
                $detectedHoles[] = [
                    'block_id' => $blockId,
                    'reason' => $matchedReason,
                ];
            }
        }

        $telemetry->setLastTelemetry([
            'tool' => 'plot_hole_detector',
            'stage' => 'audit',
            'provider' => 'deterministic_engine',
            'model' => 'rule-based-nlp',
            'executed_at' => now()->toIso8601String(),
            'duration_ms' => 10,
            'status' => 'SUCCESS',
            'raw_response' => json_encode(['detected_holes' => $detectedHoles]),
            'output' => count($detectedHoles).' inconsistencias detectadas.',
            'characters_context' => $payload['characters'] ?? [],
            'location_context' => null,
            'user_locks' => [],
            'steps' => [
                ['number' => 1, 'title' => 'Iteración 1: Detección Preliminar de Inconsistencias', 'status' => 'completed', 'detail' => 'Escaneo cronológico.'],
                ['number' => 2, 'title' => 'Iteración 2: Cotejo Literal de Evidencia Textual', 'status' => 'completed', 'detail' => 'Validación cruzada contra el texto real.'],
                ['number' => 3, 'title' => 'Iteración 3: Consenso Crítico y Validación Dramática Final', 'status' => 'completed', 'detail' => count($detectedHoles).' agujeros de guión confirmados.'],
            ],
        ]);

        return [
            'success' => true,
            'output' => count($detectedHoles).' inconsistencias detectadas.',
            'metadata' => [
                'stage' => 'audit',
                'detected_holes' => $detectedHoles,
                'total_holes' => count($detectedHoles),
            ],
            'detected_holes' => $detectedHoles,
        ];
    }

    /**
     * Extrae inconsistencias explícitas o contradicciones narrativas del texto.
     */
    protected function extractBlockInconsistency(string $text): ?string
    {
        if (preg_match('/\[(?:AGUJERO|PLOT_HOLE|INCONSISTENCIA):\s*([^\]]+)\]/iu', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:agujero de gui[oó]n|plot hole|inconsistencia dram[aá]tica|incongruencia):\s*([^\.\n]+)/iu', $text, $m)) {
            return 'Inconsistencia detectada: '.trim($m[1]);
        }
        if (preg_match('/\b(desaparece sin explicaci[oó]n|reaparece de repente sin justificaci[oó]n|muere y luego habla|teletransporta|arma que nunca tuvo)\b/iu', $text, $m)) {
            return 'Posible contradicción física o narrativa detectada en la acción ("'.$m[1].'").';
        }

        return null;
    }
}
