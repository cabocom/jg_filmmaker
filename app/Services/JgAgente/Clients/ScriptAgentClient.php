<?php

namespace App\Services\JgAgente\Clients;

use App\Services\JgAgente\AgentHttpTransport;
use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;
use App\Services\JgAgente\HmacSigner;
use App\Services\JgAgente\TelemetryRecorder;

class ScriptAgentClient
{
    public function __construct(
        protected AgentHttpTransport $transport,
        protected HmacSigner $signer,
        protected DeterministicFallbackEngine $fallbackEngine,
        protected TelemetryRecorder $telemetry
    ) {}

    /**
     * Estructura notas y texto bruto en tratamiento dramático en presente indicativo.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>|null  $location
     * @param  array<string, mixed>|string  $userLocks
     */
    public function structureTreatment(
        string $rawText,
        array $characters = [],
        ?array $location = null,
        array|string $userLocks = []
    ): string {
        $parsedLocks = $this->parseUserLocks($userLocks);
        $payload = [
            'raw_text' => $rawText,
            'characters_context' => $characters,
            'location_context' => $location,
            'user_locks' => $parsedLocks,
        ];

        $result = $this->transport->callTool(
            'treatment_structuring',
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn ($t, $l, $s) => $this->applyUserLocks($t, $l, $s),
            fn ($t) => []
        );

        return (string) ($result['output'] ?? '');
    }

    /**
     * Convierte texto estructurado en guion literario canónico Fountain.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>|string  $userLocks
     * @param  array<string, mixed>|null  $location
     */
    public function formatScreenplay(
        string $structuredText,
        array $characters = [],
        array|string $userLocks = [],
        ?array $location = null
    ): string {
        $parsedLocks = $this->parseUserLocks($userLocks);
        $payload = [
            'structured_text' => $structuredText,
            'characters_context' => $characters,
            'user_locks' => $parsedLocks,
            'location_context' => $location,
        ];

        $result = $this->transport->callTool(
            'screenplay_formatter',
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn ($t, $l, $s) => $this->applyUserLocks($t, $l, $s),
            fn ($t) => []
        );

        return (string) ($result['output'] ?? '');
    }

    /**
     * Aplica los candados de usuario (pinned_terms y manual_diffs) a un texto.
     *
     * @param  array<string, mixed>  $userLocks
     */
    public function applyUserLocks(string $text, array $userLocks, string $stage): string
    {
        if (empty($text) || empty($userLocks)) {
            return $text;
        }

        $result = $this->applyPinnedTerms($text, (array) ($userLocks['pinned_terms'] ?? []));

        return $this->applyManualDiffs($result, (array) ($userLocks['manual_diffs'] ?? []), $stage);
    }

    /**
     * Aplica reemplazo seguro de términos fijos.
     *
     * @param  array<string, mixed>  $pinnedTerms
     */
    protected function applyPinnedTerms(string $text, array $pinnedTerms): string
    {
        $result = $text;
        $count = 0;

        foreach ($pinnedTerms as $term => $replacement) {
            if ($count >= 50) {
                break;
            }
            $termStr = (string) $term;
            $repStr = (string) $replacement;

            if ($termStr === '' || $repStr === '' || mb_strlen($termStr) > 80 || mb_strlen($repStr) > 80) {
                continue;
            }

            $quoted = preg_quote($termStr, '/');
            $startBoundary = preg_match('/^\w/u', $termStr) ? '(?<![\p{L}\p{N}_])' : '';
            $endBoundary = preg_match('/\w$/u', $termStr) ? '(?![\p{L}\p{N}_])' : '';
            $pattern = '/'.$startBoundary.$quoted.$endBoundary.'/iu';

            $result = (string) preg_replace_callback($pattern, fn () => $repStr, $result);
            $count++;
        }

        return $result;
    }

    /**
     * Aplica diferencias manuales fijadas por etapa.
     *
     * @param  list<mixed>  $manualDiffs
     */
    protected function applyManualDiffs(string $text, array $manualDiffs, string $stage): string
    {
        $result = $text;

        foreach ($manualDiffs as $diff) {
            if (! is_array($diff)) {
                continue;
            }
            $diffStage = $diff['stage'] ?? $diff['target'] ?? null;
            if ($diffStage && $diffStage !== $stage) {
                continue;
            }
            $original = $diff['original_ai'] ?? $diff['original'] ?? null;
            $override = $diff['user_override'] ?? $diff['modified'] ?? null;
            if (! empty($original) && $override !== null) {
                $pos = strpos($result, (string) $original);
                if ($pos !== false) {
                    $result = substr_replace($result, (string) $override, $pos, strlen((string) $original));
                }
            }
        }

        return $result;
    }

    /**
     * Normaliza formato de candados de usuario a array.
     */
    protected function parseUserLocks(array|string $userLocks): array
    {
        if (is_array($userLocks)) {
            return $userLocks;
        }

        $decoded = is_string($userLocks) ? json_decode($userLocks, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
