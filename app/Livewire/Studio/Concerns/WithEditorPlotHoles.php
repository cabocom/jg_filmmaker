<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\AnalyzePlotHolesAction;
use App\Services\JgAgente\AgentClientService;

trait WithEditorPlotHoles
{
    use WithEditorPlotHoleJustification;

    public function promptAnalyzePlotHoles(): void
    {
        $scenes = $this->project->scenes()
            ->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])
            ->orderBy('scene_order')
            ->get();

        $allBlocks = $scenes->flatMap(fn ($s) => $s->scriptBlocks);
        $blockCount = 0;
        $totalChars = 0;

        foreach ($allBlocks as $block) {
            $text = trim(($block->raw_text ?? '').' '.($block->structured_text ?? '').' '.($block->screenplay_content ?? ''));
            if (! empty($text)) {
                $blockCount++;
                $totalChars += mb_strlen($text);
            }
        }

        if ($blockCount === 0) {
            session()->flash('warning', 'No hay bloques con contenido en el proyecto para auditar.');

            return;
        }

        // Estimación heurística: prompt (longitud texto / 4 + contexto biblia ~350) + output estimado (~750)
        $estimatedPromptTokens = (int) ceil($totalChars / 4) + 350;
        $estimatedResponseTokens = 750;
        $this->estimatedPlotHoleTokens = $estimatedPromptTokens + $estimatedResponseTokens;
        $this->estimatedBlocksToAudit = $blockCount;

        $user = auth()->user() ?? $this->project->user;
        if ($user && ! $user->hasDailyTokensAvailable($this->estimatedPlotHoleTokens)) {
            $available = $user->getTokensAvailableToday();
            $limit = $user->getDailyTokenLimit();
            session()->flash('error', "Saldo insuficiente de tokens para auditar el proyecto ({$available} disponibles, ~{$this->estimatedPlotHoleTokens} requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.');

            return;
        }

        $this->showPlotHoleConfirmModal = true;
    }

    /**
     * Cancela la auditoría de agujeros de guión y cierra el modal.
     */
    public function cancelAnalyzePlotHoles(): void
    {
        $this->showPlotHoleConfirmModal = false;
    }

    /**
     * Confirma la auditoría de agujeros de guión tras la aceptación del coste en tokens.
     */
    public function confirmAnalyzePlotHoles(): void
    {
        $this->showPlotHoleConfirmModal = false;
        $this->analyzePlotHoles();
    }

    public function analyzePlotHoles(?AgentClientService $agentClient = null, ?AnalyzePlotHolesAction $action = null): void
    {
        $result = ($action ?? app(AnalyzePlotHolesAction::class))->execute($this->project, auth()->user(), $agentClient);

        if ($result['activity_log']) {
            $log = $result['activity_log'];
            $this->logActivity($log['action'], $log['entity_type'], $log['entity_id'], $log['description'], $log['details']);
        }

        session()->flash($result['flash_type'], $result['message']);
    }

    /**
     * Alterna la visibilidad de los mensajes/tarjetas de agujeros de guión en la interfaz.
     */
    public function togglePlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = ! $this->showPlotHoleAlerts;
        $status = $this->showPlotHoleAlerts ? 'activados' : 'desactivados';
        session()->flash('info', "Avisos de agujeros de guión {$status}.");
    }

    /**
     * Hace visibles los avisos de agujeros de guión.
     */
    public function showPlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = true;
        session()->flash('info', 'Avisos de agujeros de guión visibles.');
    }

    /**
     * Oculta los avisos de agujeros de guión.
     */
    public function hidePlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = false;
        session()->flash('info', 'Avisos de agujeros de guión ocultos.');
    }

    /**
     * Limpia todas las marcas de agujeros de guión activas en los bloques del proyecto.
     */
    public function clearPlotHoles(?AnalyzePlotHolesAction $action = null): void
    {
        ($action ?? app(AnalyzePlotHolesAction::class))->clearPlotHoles($this->project);
        $this->logActivity('clear_plot_holes', 'Project', $this->project->id, 'Todas las marcas de agujeros de guión eliminadas del proyecto');

        session()->flash('success', 'Todas las marcas de agujeros de guión han sido eliminadas del proyecto.');
    }
}
