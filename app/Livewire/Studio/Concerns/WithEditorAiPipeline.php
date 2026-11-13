<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ExecuteBlockPipelineAction;
use App\Exceptions\QuotaExceededException;
use App\Services\ScriptPipelineService;

trait WithEditorAiPipeline
{
    public function structureBlock(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = $this->projectBlock($blockId);

        if ($block->isStageLocked('structured')) {
            session()->flash('error', "El tratamiento del Bloque {$block->block_order} está protegido (🔒) por modificaciones manuales. Desbloquéalo primero para permitir que la IA lo regenere.");

            return;
        }

        $previousStructuredText = $block->structured_text;

        try {
            $pipelineService->transitionToStructured($block);
        } catch (QuotaExceededException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->activeBlockTabs[$block->id] = 'structured';

        if ($this->detailBlockId === $block->id) {
            $this->editStructuredText = (string) $block->fresh()->structured_text;
            $this->detailActiveTab = 'structured';
        }

        if ($this->editingBlockId === $block->id) {
            $this->editStructuredText = (string) $block->fresh()->structured_text;
            $this->editStage = 'structured';
        }

        $freshBlock = $block->fresh();
        $scene = $freshBlock->scene;
        $this->logActivity('structure_block_ai', 'ScriptBlock', $block->id, "Bloque #{$block->block_order} de Escena #{$scene?->scene_order} estructurado a tratamiento cinematográfico con IA", [
            'block_id' => $block->id,
            'scene_id' => $scene?->id,
            'scene_order' => $scene?->scene_order,
            'scene_slugline' => $scene?->slugline,
            'block_order' => $block->block_order,
            'stage' => 'structured',
            'previous_structured_text' => $previousStructuredText,
            'raw_text' => $freshBlock->raw_text,
            'structured_text' => $freshBlock->structured_text,
        ]);

        session()->flash('success', "Bloque {$block->block_order} estructurado a tratamiento cinematográfico.");
    }

    public function formatBlockToScreenplay(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = $this->projectBlock($blockId);

        if ($block->isStageLocked('screenplay')) {
            session()->flash('error', "El guión del Bloque {$block->block_order} está protegido (🔒) por modificaciones manuales. Desbloquéalo primero en la pestaña de Guión para permitir que la IA lo regenere.");

            return;
        }

        $previousScreenplayContent = $block->screenplay_content;

        try {
            $pipelineService->transitionToScreenplay($block);
        } catch (QuotaExceededException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->activeBlockTabs[$block->id] = 'screenplay';

        if ($this->detailBlockId === $block->id) {
            $this->editScreenplayContent = (string) $block->fresh()->screenplay_content;
            $this->detailActiveTab = 'screenplay';
        }

        if ($this->editingBlockId === $block->id) {
            $this->editScreenplayContent = (string) $block->fresh()->screenplay_content;
            $this->editStage = 'screenplay';
        }

        $freshBlock = $block->fresh();
        $scene = $freshBlock->scene;
        $this->logActivity('format_screenplay_ai', 'ScriptBlock', $block->id, "Bloque #{$block->block_order} de Escena #{$scene?->scene_order} formateado a guion literario con IA", [
            'block_id' => $block->id,
            'scene_id' => $scene?->id,
            'scene_order' => $scene?->scene_order,
            'scene_slugline' => $scene?->slugline,
            'block_order' => $block->block_order,
            'stage' => 'screenplay',
            'previous_screenplay_content' => $previousScreenplayContent,
            'structured_text' => $freshBlock->structured_text,
            'screenplay_content' => $freshBlock->screenplay_content,
        ]);

        session()->flash('success', "Bloque {$block->block_order} formateado a guion literario.");
    }

    public function toggleStageLock(string $blockId, string $stage, ?ExecuteBlockPipelineAction $action = null): void
    {
        if (! in_array($stage, ['structured', 'screenplay', 'shots'], true)) {
            return;
        }

        $block = $this->projectBlock($blockId);
        $res = ($action ?? app(ExecuteBlockPipelineAction::class))->toggleStageLock($block, $stage);

        $this->logActivity('toggle_stage_lock', 'ScriptBlock', $block->id, "Candado de etapa '{$stage}' alternado en Bloque #{$block->block_order}", [
            'block_id' => $block->id,
            'stage' => $stage,
            'locked_fields' => $block->user_locks['locked_fields'] ?? [],
        ]);

        session()->flash($res['flash_type'], $res['message']);
    }

    public function breakdownBlockShots(string $blockId, ScriptPipelineService $pipelineService, ?ExecuteBlockPipelineAction $action = null): void
    {
        $block = $this->projectBlock($blockId);

        if ($block->isStageLocked('shots')) {
            session()->flash('error', "El desglose de planos del Bloque {$block->block_order} está protegido (🔒) por candado. Desbloquéalo primero para regenerar los planos con IA.");

            return;
        }

        try {
            $count = ($action ?? app(ExecuteBlockPipelineAction::class))->breakdownBlockShots($block, $pipelineService);
        } catch (QuotaExceededException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->activeBlockTabs[$block->id] = 'shots';

        if ($this->detailBlockId === $block->id) {
            $this->detailActiveTab = 'shots';
        }

        session()->flash('success', "Bloque {$block->block_order} desglosado: {$count} planos sincronizados.");
    }

    /**
     * Calcula la estimación de tokens para el pipeline de la escena y abre el modal de confirmación previa.
     */
    public function promptBlockAction(string $blockId, string $action): void
    {
        if (! in_array($action, ['structure', 'screenplay', 'shots'], true)) {
            return;
        }

        $block = $this->projectBlock($blockId);

        $lockKey = match ($action) {
            'structure' => 'structured',
            'screenplay' => 'screenplay',
            'shots' => 'shots',
        };

        if ($block->isStageLocked($lockKey)) {
            $stageName = match ($action) {
                'structure' => 'tratamiento',
                'screenplay' => 'guión',
                'shots' => 'planos',
            };
            session()->flash('error', "El {$stageName} del Bloque {$block->block_order} está protegido (🔒) por modificaciones manuales. Desbloquéalo primero para regenerar con IA.");

            return;
        }

        $sourceText = match ($action) {
            'structure' => (string) $block->raw_text,
            'screenplay' => ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text,
            'shots' => ! empty($block->screenplay_content) ? $block->screenplay_content : (! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text),
        };

        $estimatedTokens = max(50, (int) ceil(mb_strlen(trim($sourceText)) / 4) + 150);

        $user = auth()->user() ?? $this->project->user;
        if ($user && ! $user->hasDailyTokensAvailable($estimatedTokens)) {
            $available = $user->getTokensAvailableToday();
            $limit = $user->getDailyTokenLimit();
            session()->flash('error', "Saldo insuficiente de tokens para procesar el bloque ({$available} disponibles, ~{$estimatedTokens} requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.');

            return;
        }

        $this->confirmingBlockId = $block->id;
        $this->confirmingBlockAction = $action;
        $this->confirmingBlockOrder = $block->block_order;
        $this->estimatedBlockActionTokens = $estimatedTokens;
        $this->showBlockActionConfirmModal = true;
    }

    public function cancelBlockAction(): void
    {
        $this->showBlockActionConfirmModal = false;
        $this->confirmingBlockId = null;
        $this->confirmingBlockAction = null;
        $this->confirmingBlockOrder = null;
        $this->estimatedBlockActionTokens = 0;
    }

    public function confirmBlockAction(ScriptPipelineService $pipelineService): void
    {
        $blockId = $this->confirmingBlockId;
        $action = $this->confirmingBlockAction;

        $this->cancelBlockAction();

        if (! $blockId || ! $action) {
            return;
        }

        match ($action) {
            'structure' => $this->structureBlock($blockId, $pipelineService),
            'screenplay' => $this->formatBlockToScreenplay($blockId, $pipelineService),
            'shots' => $this->breakdownBlockShots($blockId, $pipelineService),
        };
    }

    /**
     * Ejecuta el pipeline completo de IA para todos los bloques de la escena especificada
     * (Bruto ➔ Tratamiento ➔ Guión literario ➔ Desglose de planos).
     */
    public function setBlockStage(string $blockId, string $stage): void
    {
        if (! in_array($stage, ['raw', 'structured', 'screenplay', 'shots', 'editing'], true)) {
            return;
        }

        $block = $this->projectBlock($blockId);
        $block->update(['stage' => $stage]);
    }
}
