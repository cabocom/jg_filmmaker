<?php

declare(strict_types=1);

namespace App\Services\JgAgente;

class TelemetryRecorder
{
    /**
     * Registro de telemetría de la última invocación de IA.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $lastTelemetry = null;

    /**
     * Obtiene los metadatos y trazabilidad de la última invocación de IA.
     *
     * @return array<string, mixed>|null
     */
    public function getLastTelemetry(): ?array
    {
        return $this->lastTelemetry;
    }

    /**
     * Almacena los metadatos de telemetría de la invocación actual.
     *
     * @param  array<string, mixed>|null  $telemetry
     */
    public function setLastTelemetry(?array $telemetry): void
    {
        $this->lastTelemetry = $telemetry;
    }

    /**
     * Registra la telemetría de una invocación (incluyendo aciertos de caché semántica).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    public function record(string $toolName, array $payload, array $result, int $durationMs = 0): void
    {
        $rawTelemetry = is_array($result['telemetry'] ?? null) ? $result['telemetry'] : [];
        $locks = is_array($payload['user_locks'] ?? null) ? $this->sanitizeTelemetryLocks($payload['user_locks']) : [];

        $this->setLastTelemetry(array_merge($rawTelemetry, [
            'tool' => $toolName,
            'duration_ms' => $durationMs,
            'cache_hit' => (bool) ($result['cache_hit'] ?? $durationMs === 0),
            'user_locks' => $locks,
        ]));
    }

    /**
     * Sanitiza los candados almacenados en telemetría para retener solo términos de autoría.
     *
     * @param  array<string, mixed>  $locks
     * @return array{pinned_terms: array<string, string>, manual_diffs: array<int, mixed>, locked_fields: list<string>}
     */
    public function sanitizeTelemetryLocks(array $locks): array
    {
        return [
            'pinned_terms' => (array) ($locks['pinned_terms'] ?? []),
            'manual_diffs' => (array) ($locks['manual_diffs'] ?? []),
            'locked_fields' => (array) ($locks['locked_fields'] ?? []),
        ];
    }
}
