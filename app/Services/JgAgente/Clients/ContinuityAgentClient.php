<?php

namespace App\Services\JgAgente\Clients;

use App\Services\JgAgente\AgentHttpTransport;
use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;
use App\Services\JgAgente\HmacSigner;
use App\Services\JgAgente\TelemetryRecorder;

class ContinuityAgentClient
{
    public function __construct(
        protected AgentHttpTransport $transport,
        protected HmacSigner $signer,
        protected DeterministicFallbackEngine $fallbackEngine,
        protected TelemetryRecorder $telemetry
    ) {}

    /**
     * Audita y detecta inconsistencias dramáticas y agujeros de guión en la secuencia de bloques del proyecto.
     *
     * @param  array<int, array<string, mixed>>  $blocksData
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<int, array<string, mixed>>  $locations
     * @return array{detected_holes: array<int, array{block_id: string|int, reason: string}>, total_holes: int}
     */
    public function auditPlotHoles(
        array $blocksData,
        array $characters = [],
        array $locations = [],
        ?callable $toolCaller = null
    ): array {
        $payload = [
            'blocks' => $blocksData,
            'characters' => $characters,
            'locations' => $locations,
        ];

        if ($toolCaller) {
            $result = $toolCaller('plot_hole_detector', $payload);
        } else {
            $result = $this->transport->callTool(
                'plot_hole_detector',
                $payload,
                $this->signer,
                $this->fallbackEngine,
                $this->telemetry,
                fn ($t, $l, $s) => $t,
                fn ($t) => []
            );
        }

        $rawHoles = (array) ($result['metadata']['detected_holes'] ?? ($result['detected_holes'] ?? []));

        $blocksIndex = [];
        foreach ($blocksData as $b) {
            $blocksIndex[(string) ($b['id'] ?? '')] = $b;
        }

        $verifiedHoles = [];
        foreach ($rawHoles as $hole) {
            $bId = (string) ($hole['block_id'] ?? '');
            $reason = trim((string) ($hole['reason'] ?? ''));

            if (empty($bId) || empty($reason) || ! isset($blocksIndex[$bId])) {
                continue;
            }

            $block = $blocksIndex[$bId];
            $blockText = (string) ($block['text'] ?? ($block['screenplay_content'] ?? ($block['structured_text'] ?? ($block['raw_text'] ?? ''))));

            if ($this->isFalsePositiveReason($reason, $characters, $blockText)) {
                continue;
            }

            $verifiedHoles[] = [
                'block_id' => $bId,
                'reason' => $reason,
            ];
        }

        return [
            'detected_holes' => $verifiedHoles,
            'total_holes' => count($verifiedHoles),
        ];
    }

    /**
     * Filtro anti-falsos positivos de tipografía y presencia literal de personajes.
     *
     * @param  list<mixed>  $characters
     */
    protected function isFalsePositiveReason(string $reason, array $characters, string $blockText): bool
    {
        if (preg_match('/(?:may[uú]scula|min[uú]scula|capitaliz|escrito\s+como|inconsistencia\s+de\s+nombre|inconsistencia\s+en\s+el\s+nombre|diferente\s+a\s*["\'][^"\']+["\']|nombre\s+del\s+(?:personaje|objeto|elemento)|mismo\s+personaje\s+con\s+distinto\s+nombre)/iu', $reason)) {
            return true;
        }

        if (preg_match_all('/["«\']([^"»\']+)["»\']/u', $reason, $quotedMatches)) {
            $quoted = $quotedMatches[1];
            if (count($quoted) >= 2) {
                if (mb_strtolower(trim($quoted[0])) === mb_strtolower(trim($quoted[1]))) {
                    return true;
                }
            }
        }

        foreach ($characters as $char) {
            $charName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
            if (! empty($charName) && preg_match('/\b'.preg_quote($charName, '/').'\b/iu', $reason)) {
                if (! preg_match('/\b'.preg_quote($charName, '/').'\b/iu', $blockText)) {
                    return true;
                }
            }
        }

        return false;
    }
}
