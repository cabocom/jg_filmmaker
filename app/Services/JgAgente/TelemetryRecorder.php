<?php

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
