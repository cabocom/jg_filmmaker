<?php

namespace App\Services\JgAgente\Clients;

use App\Services\JgAgente\AgentHttpTransport;
use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;
use App\Services\JgAgente\HmacSigner;
use App\Services\JgAgente\TelemetryRecorder;

class StoryboardAgentClient
{
    public function __construct(
        protected AgentHttpTransport $transport,
        protected HmacSigner $signer,
        protected DeterministicFallbackEngine $fallbackEngine,
        protected TelemetryRecorder $telemetry
    ) {}

    /**
     * Desglosa el guion literario en planos técnicos con sintaxis '--- shot N (tipo) ---'.
     *
     * @return array{annotated_text: string, shots: array<int, array{shot_number: int, shot_type: string, description: string}>}
     */
    public function breakdownShots(string $screenplayText): array
    {
        $payload = [
            'screenplay_text' => $screenplayText,
        ];

        $result = $this->transport->callTool(
            'shot_breakdown_detector',
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn ($t, $l, $s) => $t,
            fn ($t) => $this->parseShotsFromText($t)
        );

        $annotatedText = (string) ($result['output'] ?? '');
        $shots = (array) ($result['metadata']['shots'] ?? []);

        if (empty($shots) && ! empty($annotatedText)) {
            $shots = $this->parseShotsFromText($annotatedText);
        }

        return [
            'annotated_text' => $annotatedText,
            'shots' => $shots,
        ];
    }

    /**
     * Parsea planos anotados con el delimitador canónico '--- shot N (tipo) ---'.
     *
     * @return array<int, array{shot_number: int, shot_type: string, description: string}>
     */
    public function parseShotsFromText(string $text): array
    {
        $pattern = '/---\s*shot(?:\s+\d+)?\s*(?:\(([^)]+)\))?\s*---\s*\n*(.*?)(?=(?:---\s*shot)|$)/is';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $shots = [];
        $autoNum = 1;
        foreach ($matches as $match) {
            $type = ! empty($match[1]) ? strtoupper(trim($match[1])) : 'PM';
            $desc = trim($match[2] ?? '');

            if ($desc === '' || preg_match('/^(INT|EXT|INT\/EXT|EST)[\.\s]/i', $desc)) {
                continue;
            }

            $shots[] = [
                'shot_number' => $autoNum,
                'shot_type' => $type,
                'description' => $desc,
            ];
            $autoNum++;
        }

        return $shots;
    }
}
