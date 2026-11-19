<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasScriptBlockPlotHoles
{
    /**
     * Comprueba si el bloque tiene un agujero de guión detectado activo.
     */
    public function hasPlotHole(): bool
    {
        return filled($this->plot_hole);
    }

    /**
     * Devuelve el identificador de la etapa/step donde se detectó el agujero (raw, structured, screenplay).
     */
    public function getPlotHoleStage(): ?string
    {
        if (empty($this->plot_hole)) {
            return null;
        }

        $locks = $this->user_locks ?? [];

        return $locks['plot_hole_stage'] ?? 'raw';
    }

    /**
     * Devuelve la etiqueta legible de la etapa donde se detectó el agujero de guión.
     */
    public function getPlotHoleStageLabel(): string
    {
        return match ($this->getPlotHoleStage()) {
            'structured' => 'Tratamiento',
            'screenplay' => 'Guion',
            'raw' => 'Idea (Bruto)',
            default => 'Idea (Bruto)',
        };
    }

    /**
     * Obtiene el listado de agujeros de guión justificados por el autor para este bloque.
     *
     * @return array<int, array{id: string, hole_reason: string, justification: string, justified_at: string}>
     */
    public function getJustifiedPlotHoles(): array
    {
        $locks = $this->user_locks ?? [];

        return (array) ($locks['justified_plot_holes'] ?? []);
    }

    /**
     * Comprueba si el bloque tiene agujeros justificados registrados.
     */
    public function hasJustifiedPlotHoles(): bool
    {
        return ! empty($this->getJustifiedPlotHoles());
    }
}
