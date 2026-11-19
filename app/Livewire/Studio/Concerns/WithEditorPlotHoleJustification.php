<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\JustifyPlotHoleAction;

trait WithEditorPlotHoleJustification
{
    public function openJustifyModal(string $blockId): void
    {
        $block = $this->projectBlock($blockId);
        $this->justifyingBlockId = $block->id;
        $this->justifyingHoleReason = (string) ($block->plot_hole ?? '');
        $this->justificationText = '';
        $this->showJustifyModal = true;
    }

    public function closeJustifyModal(): void
    {
        $this->showJustifyModal = false;
        $this->justifyingBlockId = null;
        $this->justifyingHoleReason = '';
        $this->justificationText = '';
    }

    public function savePlotHoleJustification(?JustifyPlotHoleAction $action = null): void
    {
        if (! $this->justifyingBlockId || empty(trim($this->justificationText))) {
            session()->flash('error', 'Debes introducir una explicación para justificar y retirar el agujero de guión.');

            return;
        }

        $block = $this->projectBlock($this->justifyingBlockId);
        $log = ($action ?? app(JustifyPlotHoleAction::class))->justify($block, $this->justificationText, $this->justifyingHoleReason);
        $this->logActivity($log['action'], $log['entity_type'], $log['entity_id'], $log['description'], $log['details']);

        $blockOrder = $block->block_order;
        $this->closeJustifyModal();
        session()->flash('success', "Agujero de guión justificado y retirado del Bloque #{$blockOrder}. Ahora figura en 'Agujeros justificados'.");
    }
}
