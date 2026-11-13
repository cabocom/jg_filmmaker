<?php

namespace App\Livewire\Studio\Concerns;

use App\Models\ScriptBlock;
use App\Models\Shot;
use Illuminate\Support\Facades\DB;

trait WithEditorShots
{
    protected function projectShot(string $id): Shot
    {
        return Shot::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))->findOrFail($id);
    }

    /**
     * Obtiene una toma de rodaje verificando que pertenezca a este proyecto.
     *
     * @param  array<int, string>  $with
     */
    public function deleteShot(string $shotId): void
    {
        $shot = $this->projectShot($shotId);
        $block = $shot->scriptBlock;
        $shotNumber = $shot->shot_number;

        DB::transaction(function () use ($shot, $block) {
            $shot->takes()->delete();
            $shot->delete();

            if ($block) {
                // Reindexar planos correlativamente dentro del bloque
                $remaining = $block->shots()->orderBy('shot_number')->get();
                $idx = 1;
                foreach ($remaining as $s) {
                    if ($s->shot_number !== $idx) {
                        $s->update(['shot_number' => $idx]);
                    }
                    $idx++;
                }
            }
        });

        session()->flash('success', "Plano #{$shotNumber} eliminado correctamente.");
    }

    public function startEditShot(string $shotId): void
    {
        $shot = $this->projectShot($shotId);
        $this->editingShotId = $shot->id;
        $this->editingShotType = $shot->shot_type;
        $this->editingShotDescription = (string) $shot->description;
    }

    public function cancelEditShot(): void
    {
        $this->editingShotId = null;
        $this->editingShotType = 'PM';
        $this->editingShotDescription = '';
    }

    public function saveEditShot(): void
    {
        if (! $this->editingShotId) {
            return;
        }

        $this->validate([
            'editingShotType' => 'required',
            'editingShotDescription' => 'required|min:3',
        ]);

        $shot = $this->projectShot($this->editingShotId);
        $shot->update([
            'shot_type' => strtoupper(trim($this->editingShotType)),
            'description' => trim($this->editingShotDescription),
        ]);

        $num = $shot->shot_number;
        $this->cancelEditShot();
        session()->flash('success', "Plano #{$num} actualizado correctamente.");
    }

    public function setBlockTab(string $blockId, string $tab): void
    {
        // Si el usuario está editando este bloque, no permitir cambio de pestaña hasta guardar o cancelar
        if ($this->editingBlockId === $blockId) {
            return;
        }

        $this->activeBlockTabs[$blockId] = $tab;
        $this->openBlockDetails($blockId, $tab);
    }

    public function addPinnedTerm(string $blockId, ?string $term = null, ?string $replacement = null): void
    {
        $term = trim((string) ($term ?? $this->newPinnedTerm));
        $replacement = trim((string) ($replacement ?? $this->newPinnedReplacement));

        if ($term === '' || $replacement === '') {
            session()->flash('error', 'El término original y su sustituto no pueden estar vacíos.');

            return;
        }

        if (mb_strlen($term) > 80 || mb_strlen($replacement) > 80) {
            session()->flash('error', 'Los términos fijados no pueden exceder los 80 caracteres de longitud.');

            return;
        }

        $block = $this->projectBlock($blockId);

        $success = false;
        $block->mutateUserLocks(function (array $userLocks) use ($term, $replacement, &$success) {
            $pinned = $userLocks['pinned_terms'] ?? [];
            if (! is_array($pinned)) {
                $pinned = [];
            }

            if (count($pinned) >= 50 && ! isset($pinned[$term])) {
                session()->flash('error', 'Se ha alcanzado el límite máximo de 50 términos fijados para este bloque.');

                return $userLocks;
            }

            $pinned[$term] = $replacement;
            $userLocks['pinned_terms'] = $pinned;
            $success = true;

            return $userLocks;
        });

        if ($success) {
            $this->reset(['newPinnedTerm', 'newPinnedReplacement']);
            session()->flash('success', "Término fijo '{$term}' fijado como '{$replacement}'.");
        }
    }

    public function removePinnedTerm(string $blockId, string $term): void
    {
        $block = $this->projectBlock($blockId);
        $block->mutateUserLocks(function (array $userLocks) use ($term) {
            if (isset($userLocks['pinned_terms'][$term])) {
                unset($userLocks['pinned_terms'][$term]);
            }

            return $userLocks;
        });
        session()->flash('success', "Término fijo '{$term}' eliminado.");
    }

    public function startNewShotForBlock(string $blockId): void
    {
        $this->newShotBlockId = $blockId;
        $this->showNewShotModal = true;
    }

    public function getActiveTabForBlock(ScriptBlock $block): string
    {
        if (isset($this->activeBlockTabs[$block->id])) {
            return $this->activeBlockTabs[$block->id];
        }

        // Al entrar al proyecto carga siempre la primera pestaña ('raw')
        return 'raw';
    }
}
