<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ExecuteBlockPipelineAction;
use App\Models\ScriptBlock;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait WithEditorBlockDrawer
{
    public function selectBlock(string $blockId): void
    {
        $this->selectedBlockId = $blockId;
        $this->dispatch('scroll-to-selected-block', blockId: $blockId);
    }

    #[On('open-block-details')]
    public function openBlockDetails(string $blockId, ?string $tab = null): void
    {
        $block = $this->projectBlock($blockId);
        $this->detailBlockId = $block->id;
        $this->selectedBlockId = $block->id;
        $this->isEditingInDrawer = false;

        $targetTab = $tab ?: match ($this->pipelineStage) {
            'idea' => 'raw',
            'structured' => 'structured',
            'screenplay' => 'screenplay',
            'shots' => 'shots',
            default => 'raw',
        };

        $this->detailActiveTab = $targetTab;
        $this->editLockVersion = $block->lock_version ?? 1;
        $this->editRawText = (string) ($block->raw_text ?? '');
        $this->editStructuredText = (string) ($block->structured_text ?? '');
        $this->editScreenplayContent = (string) ($block->screenplay_content ?? '');
        $this->editStage = (string) ($block->stage ?? 'raw');
    }

    public function closeBlockDetails(): void
    {
        $this->detailBlockId = null;
        $this->isEditingInDrawer = false;
        $this->editLockVersion = null;
    }

    public function setDetailTab(string $tab): void
    {
        if (in_array($tab, ['raw', 'structured', 'screenplay', 'shots', 'diffs', 'locks'], true)) {
            $this->detailActiveTab = $tab;
        }
    }

    public function startEditingInDrawer(): void
    {
        if (! $this->detailBlockId) {
            return;
        }

        $block = $this->projectBlock($this->detailBlockId);
        $this->editLockVersion = $block->lock_version ?? 1;
        $this->editRawText = (string) ($block->raw_text ?? '');
        $this->editStructuredText = (string) ($block->structured_text ?? '');
        $this->editScreenplayContent = (string) ($block->screenplay_content ?? '');
        $this->editStage = (string) ($block->stage ?? 'raw');

        // Si estamos editando en la pestaña de Planos, cargar el guión con delimitadores explícitos de planos (sin numerar)
        if ($this->detailActiveTab === 'shots') {
            $existingShots = $block->shots()
                ->orderBy('shot_number')
                ->get()
                ->reject(fn ($s) => preg_match('/^(INT|EXT|INT\/EXT|EST)[\.\s]/i', trim((string) $s->description)));

            if ($existingShots->isNotEmpty()) {
                $annotated = [];
                foreach ($existingShots as $shot) {
                    $annotated[] = "--- shot ({$shot->shot_type}) ---\n{$shot->description}";
                }
                $this->editScreenplayContent = implode("\n\n", $annotated);
            } else {
                $breakdown = app(AgentClientService::class)->breakdownShots((string) ($block->screenplay_content ?: $block->raw_text));
                $this->editScreenplayContent = $breakdown['annotated_text'] ?: (string) ($block->screenplay_content ?: $block->raw_text);
            }
        }

        $this->isEditingInDrawer = true;
    }

    public function cancelEditingInDrawer(): void
    {
        $this->isEditingInDrawer = false;
        $this->editLockVersion = null;
    }

    public function saveBlockFromDrawer(ScriptPipelineService $pipelineService): void
    {
        if (! $this->detailBlockId) {
            return;
        }

        $this->validate([
            'editStage' => ['required', Rule::in(['raw', 'structured', 'screenplay', 'shots', 'editing'])],
            'editRawText' => ['nullable', 'string', 'max:50000'],
            'editStructuredText' => ['nullable', 'string', 'max:50000'],
            'editScreenplayContent' => ['nullable', 'string', 'max:50000'],
        ]);

        $blockId = $this->detailBlockId;
        $expectedVersion = $this->editLockVersion;

        $savedBlock = DB::transaction(function () use ($blockId, $expectedVersion, $pipelineService) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($blockId)->lockForUpdate()->first();
            if (! $fresh) {
                return null;
            }

            // Detección de colisión concurrente (Optimistic Locking)
            if ($expectedVersion !== null && (int) $fresh->lock_version !== (int) $expectedVersion) {
                return false;
            }

            if (! empty($fresh->structured_text) && $this->editStructuredText !== $fresh->structured_text) {
                $pipelineService->recordManualOverride($fresh, 'structured', $fresh->structured_text, $this->editStructuredText);
            }

            if (! empty($fresh->screenplay_content) && $this->editScreenplayContent !== $fresh->screenplay_content) {
                $pipelineService->recordManualOverride($fresh, 'screenplay', $fresh->screenplay_content, $this->editScreenplayContent);
            }

            $fresh->update([
                'raw_text' => $this->editRawText,
                'structured_text' => $this->editStructuredText ?: null,
                'screenplay_content' => $this->editScreenplayContent ?: null,
                'stage' => $this->editStage,
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);

            return $fresh;
        });

        if ($savedBlock === false) {
            session()->flash('error', 'El bloque fue modificado concurrentemente por otro proceso o por la IA. Por favor, recarga y revisa los cambios antes de volver a guardar.');

            return;
        }

        if (! $savedBlock) {
            return;
        }

        // Si se guardó desde la pestaña de Planos, sincronizar los delimitadores '--- shot N (TIPO) ---' en modelos Shot
        if ($this->detailActiveTab === 'shots') {
            $parsedShots = app(AgentClientService::class)->parseShotsFromText($this->editScreenplayContent);

            if (! empty($parsedShots)) {
                DB::transaction(function () use ($savedBlock, $parsedShots) {
                    $existingShots = $savedBlock->shots()->orderBy('shot_number')->get()->keyBy('shot_number');
                    $usedShotIds = [];
                    $num = 1;

                    foreach ($parsedShots as $pShot) {
                        $existing = $existingShots->get($num);
                        if ($existing) {
                            $existing->update([
                                'shot_number' => $num,
                                'shot_type' => $pShot['shot_type'],
                                'description' => $pShot['description'],
                            ]);
                            $usedShotIds[] = $existing->id;
                        } else {
                            $new = $savedBlock->shots()->create([
                                'scene_id' => $savedBlock->scene_id,
                                'shot_number' => $num,
                                'shot_type' => $pShot['shot_type'],
                                'description' => $pShot['description'],
                            ]);
                            $usedShotIds[] = $new->id;
                        }
                        $num++;
                    }

                    // Eliminar planos excedentes eliminados en el texto
                    $savedBlock->shots()->whereNotIn('id', $usedShotIds)->each(function (Shot $s) {
                        $s->takes()->delete();
                        $s->delete();
                    });
                });

                $savedBlock->mutateUserLocks(function (array $userLocks) {
                    $lockedFields = $userLocks['locked_fields'] ?? [];
                    if (! in_array('shots', $lockedFields, true)) {
                        $lockedFields[] = 'shots';
                    }
                    $userLocks['locked_fields'] = array_values(array_unique($lockedFields));

                    return $userLocks;
                });

                $count = count($parsedShots);
                session()->flash('success', "Planos técnicos actualizados y sincronizados ({$count} planos).");
            }
        }

        $this->isEditingInDrawer = false;
        $this->editLockVersion = $savedBlock->lock_version;
        if ($this->detailActiveTab !== 'shots') {
            session()->flash('success', "Bloque #{$savedBlock->block_order} guardado correctamente.");
        }
    }

    public function startEditBlock(string $blockId): void
    {
        $block = $this->projectBlock($blockId);
        $this->editingBlockId = $block->id;
        $this->editLockVersion = $block->lock_version ?? 1;
        $this->editRawText = $block->raw_text ?? '';
        $this->editStructuredText = $block->structured_text ?? '';
        $this->editScreenplayContent = $block->screenplay_content ?? '';
        $this->editStage = $block->stage ?? 'raw';
    }

    public function saveBlock(ScriptPipelineService $pipelineService, ?ExecuteBlockPipelineAction $action = null): void
    {
        if (! $this->editingBlockId) {
            return;
        }

        $this->validate([
            'editStage' => ['required', Rule::in(['raw', 'structured', 'screenplay', 'shots', 'editing'])],
            'editRawText' => ['nullable', 'string', 'max:50000'],
            'editStructuredText' => ['nullable', 'string', 'max:50000'],
            'editScreenplayContent' => ['nullable', 'string', 'max:50000'],
        ]);

        $block = $this->projectBlock($this->editingBlockId);
        $savedBlock = ($action ?? app(ExecuteBlockPipelineAction::class))->saveBlock($block, [
            'stage' => $this->editStage,
            'raw_text' => $this->editRawText,
            'structured_text' => $this->editStructuredText,
            'screenplay_content' => $this->editScreenplayContent,
        ], $this->editLockVersion, $pipelineService);

        if ($savedBlock === false) {
            session()->flash('error', 'El bloque fue modificado concurrentemente por otro proceso o por la IA. Por favor, cancela y revisa la versión actualizada antes de guardar.');

            return;
        }

        if (! $savedBlock) {
            return;
        }

        $scene = $savedBlock->scene;
        $this->logActivity('update_block', 'ScriptBlock', $savedBlock->id, "Bloque #{$savedBlock->block_order} de Escena #{$scene?->scene_order} editado manualmente (Etapa: {$this->editStage})", [
            'block_id' => $savedBlock->id,
            'scene_id' => $scene?->id,
            'scene_order' => $scene?->scene_order,
            'scene_slugline' => $scene?->slugline,
            'block_order' => $savedBlock->block_order,
            'stage' => $this->editStage,
            'raw_text' => $this->editRawText,
            'structured_text' => $this->editStructuredText ?: null,
            'screenplay_content' => $this->editScreenplayContent ?: null,
        ]);

        $this->editingBlockId = null;
        $this->editLockVersion = null;
        session()->flash('success', "Bloque {$savedBlock->block_order} actualizado y bloqueos retenidos.");
    }
}
