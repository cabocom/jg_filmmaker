<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageScriptStructureAction;
use App\Models\Scene;
use Illuminate\Support\Facades\DB;

trait WithEditorBlockCrud
{
    public function openNewBlockModal(?int $afterOrder = null, ?string $sceneId = null): void
    {
        if ($sceneId) {
            $this->activeSceneId = $sceneId;
        }
        $this->insertAfterOrder = $afterOrder;
        $this->newBlockRawText = '';
        $this->showNewBlockModal = true;
    }

    public function closeNewBlockModal(): void
    {
        $this->showNewBlockModal = false;
        $this->insertAfterOrder = null;
        $this->newBlockRawText = '';
    }

    public function createNewBlock(): void
    {
        if (! $this->activeSceneId) {
            return;
        }

        $this->validate([
            'newBlockRawText' => 'required|string|min:2|max:50000',
        ], [
            'newBlockRawText.required' => 'Debes escribir el texto bruto o idea de la escena.',
            'newBlockRawText.min' => 'El texto debe tener al menos 2 caracteres.',
            'newBlockRawText.max' => 'El texto bruto no puede superar los 50.000 caracteres.',
        ]);

        $scene = $this->projectScene($this->activeSceneId);

        $newBlock = DB::transaction(function () use ($scene) {
            Scene::whereKey($scene->id)->lockForUpdate()->first();

            if ($this->insertAfterOrder !== null) {
                // Desplazar bloques con orden mayor que el de inserción
                $scene->scriptBlocks()
                    ->where('block_order', '>', $this->insertAfterOrder)
                    ->increment('block_order');

                $newOrder = $this->insertAfterOrder + 1;
            } else {
                $maxOrder = $scene->scriptBlocks()->max('block_order') ?? 0;
                $newOrder = $maxOrder + 1;
            }

            return $scene->scriptBlocks()->create([
                'block_order' => $newOrder,
                'raw_text' => trim($this->newBlockRawText),
                'stage' => 'raw',
                'user_locks' => [],
            ]);
        });

        $this->logActivity('create_block', 'ScriptBlock', $newBlock->id, "Bloque #{$newBlock->block_order} creado en Escena #{$scene->scene_order}", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'block_order' => $newBlock->block_order,
            'raw_text' => $newBlock->raw_text,
        ]);

        $this->selectedBlockId = $newBlock->id;
        $this->closeNewBlockModal();

        $this->dispatch('scroll-to-selected-block', blockId: $newBlock->id);
        session()->flash('success', "Bloque #{$newBlock->block_order} creado e insertado en la escena.");
    }

    public function reorderBlock(string $draggedBlockId, int $targetOrder, ?string $sceneId = null, ?ManageScriptStructureAction $action = null): void
    {
        $sceneTargetId = $sceneId ?: $this->activeSceneId;
        if (! $sceneTargetId) {
            return;
        }

        $targetScene = ($action ?? app(ManageScriptStructureAction::class))->reorderBlock(
            $this->project,
            $draggedBlockId,
            $targetOrder,
            $sceneTargetId
        );

        $this->logActivity('reorder_block', 'ScriptBlock', $draggedBlockId, "Bloque reordenado en escena a posición #{$targetOrder}", [
            'block_id' => $draggedBlockId,
            'scene_id' => $targetScene->id,
            'target_order' => $targetOrder,
        ]);

        $this->selectedBlockId = $draggedBlockId;
        session()->flash('success', 'Bloque reordenado correctamente.');
    }

    public function openDeleteBlockModal(string $blockId): void
    {
        $block = $this->projectBlock($blockId, ['scene']);
        $this->deletingBlockId = $block->id;
        $this->deletingBlockOrder = $block->block_order;
        $this->deletingBlockSceneOrder = $block->scene?->scene_order;
        $raw = trim((string) ($block->raw_text ?? ''));
        $preview = $raw ?: ($block->structured_text ?: ($block->screenplay_content ?: 'Bloque sin contenido'));
        $this->deletingBlockPreview = mb_substr($preview, 0, 120);
        $this->showDeleteBlockModal = true;
    }

    /**
     * Cierra el modal de eliminación de bloque.
     */
    public function closeDeleteBlockModal(): void
    {
        $this->reset(['showDeleteBlockModal', 'deletingBlockId', 'deletingBlockPreview', 'deletingBlockOrder', 'deletingBlockSceneOrder']);
    }

    /**
     * Confirma la eliminación irreversible del bloque, registra la auditoría y reordena.
     */
    public function confirmDeleteBlock(?ManageScriptStructureAction $action = null): void
    {
        if (! $this->deletingBlockId) {
            return;
        }

        $block = $this->projectBlock($this->deletingBlockId, ['scene.project']);
        $scene = $block->scene;

        $this->logActivity('delete_block', 'ScriptBlock', $block->id, "Bloque #{$block->block_order} de la Escena #{$scene->scene_order} ({$scene->slugline}) eliminado permanentemente", [
            'block_id' => $block->id,
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'scene_slugline' => $scene->slugline,
            'block_order' => $block->block_order,
            'stage' => $block->stage,
            'raw_text' => $block->raw_text,
            'structured_text' => $block->structured_text,
            'screenplay_content' => $block->screenplay_content,
            'plot_hole' => $block->plot_hole,
            'user_locks' => $block->user_locks,
        ]);

        if ($this->selectedBlockId === $block->id) {
            $this->selectedBlockId = null;
        }
        if ($this->editingBlockId === $block->id) {
            $this->editingBlockId = null;
        }
        if ($this->detailBlockId === $block->id) {
            $this->detailBlockId = null;
            $this->isEditingInDrawer = false;
        }

        ($action ?? app(ManageScriptStructureAction::class))->deleteBlock($block);

        $this->dispatch('bible-updated');

        $this->closeDeleteBlockModal();
        session()->flash('success', "Bloque #{$this->deletingBlockOrder} eliminado correctamente y registrado en el historial.");
    }

    /**
     * Abre el modal de advertencia para eliminar una escena.
     */
    public function addBlock(): void
    {
        $this->openNewBlockModal();
    }
}
