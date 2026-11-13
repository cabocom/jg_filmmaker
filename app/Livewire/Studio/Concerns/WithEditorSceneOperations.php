<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageScriptStructureAction;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\ScriptPipelineService;

trait WithEditorSceneOperations
{
    protected function projectBlock(string $id, array $with = []): ScriptBlock
    {
        return ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))
            ->when(! empty($with), fn ($q) => $q->with($with))
            ->findOrFail($id);
    }

    /**
     * Obtiene una escena verificando que pertenezca a este proyecto.
     */
    protected function projectScene(string $id): Scene
    {
        return $this->project->scenes()->findOrFail($id);
    }

    /**
     * Obtiene un plano técnico verificando que pertenezca a este proyecto.
     */
    public function getScenePipelineStage(string $sceneId): string
    {
        return $this->scenePipelineStages[$sceneId] ?? 'idea';
    }

    public function setScenePipelineStage(string $sceneId, string $stage): void
    {
        $this->scenePipelineStages[$sceneId] = $stage;
        $this->activeSceneId = $sceneId;

        if ($this->selectedBlockId) {
            $this->dispatch('scroll-to-selected-block', blockId: $this->selectedBlockId);
        }
    }

    public function selectScene(string $sceneId): void
    {
        $this->activeSceneId = $sceneId;
        $this->editingBlockId = null;
        $this->detailBlockId = null;
        $this->isEditingInDrawer = false;
        $this->selectedBlockId = null;
    }

    public function setActiveView(string $view): void
    {
        if (in_array($view, ['pipeline', 'idea', 'structured', 'screenplay', 'shots'])) {
            $this->activeView = $view;
        }
    }

    public function toggleScene(string $sceneId): void
    {
        if (in_array($sceneId, $this->expandedSceneIds, true)) {
            $this->expandedSceneIds = array_values(array_diff($this->expandedSceneIds, [$sceneId]));
        } else {
            $this->expandedSceneIds[] = $sceneId;
        }
    }

    public function openNewSceneModal(?int $afterOrder = null): void
    {
        $this->insertSceneAfterOrder = $afterOrder;
        $this->newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';
        $this->newSceneTimeOfDay = 'DÍA';
        $this->newSceneLocationId = null;
        $this->showNewSceneModal = true;
    }

    public function reorderScene(string $draggedSceneId, int $targetOrder, ?ManageScriptStructureAction $action = null): void
    {
        ($action ?? app(ManageScriptStructureAction::class))->reorderScene($this->project, $draggedSceneId, $targetOrder);

        $this->logActivity('reorder_scene', 'Scene', $draggedSceneId, "Escena reordenada a posición #{$targetOrder}", [
            'scene_id' => $draggedSceneId,
            'target_order' => $targetOrder,
        ]);

        session()->flash('success', 'Escena reordenada correctamente.');
    }

    public function createScene(?ManageScriptStructureAction $action = null): void
    {
        $this->validate([
            'newSceneSlugline' => 'required|min:3|max:120',
            'newSceneTimeOfDay' => 'required',
        ]);

        $scene = ($action ?? app(ManageScriptStructureAction::class))->createScene($this->project, [
            'slugline' => $this->newSceneSlugline,
            'time_of_day' => $this->newSceneTimeOfDay,
            'location_id' => $this->newSceneLocationId,
        ], $this->insertSceneAfterOrder);

        if (! in_array($scene->id, $this->expandedSceneIds, true)) {
            $this->expandedSceneIds[] = $scene->id;
        }
        $this->scenePipelineStages[$scene->id] = 'idea';

        // Sincronizar localizaciones del proyecto y notificar a la Biblia
        app(ScriptPipelineService::class)->syncProjectLocations($this->project);
        $this->dispatch('bible-updated');

        $this->logActivity('create_scene', 'Scene', $scene->id, "Escena #{$scene->scene_order} creada ({$scene->slugline})", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'slugline' => $scene->slugline,
            'time_of_day' => $scene->time_of_day,
            'location_id' => $scene->location_id,
        ]);

        $this->activeSceneId = $scene->id;
        $this->reset(['showNewSceneModal', 'newSceneLocationId']);
        $this->newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';

        session()->flash('success', "Escena {$scene->scene_order} creada.");
    }

    /**
     * Abre el modal de edición de la escena especificada.
     */
    public function openEditSceneModal(string $sceneId): void
    {
        $scene = $this->projectScene($sceneId);
        $this->editingSceneId = $scene->id;
        $this->editingSceneOrder = $scene->scene_order;
        $this->editingSceneSlugline = $scene->slugline;
        $this->editingSceneTimeOfDay = $scene->time_of_day ?: 'DÍA';
        $this->editingSceneLocationId = $scene->location_id;
        $this->showEditSceneModal = true;
    }

    /**
     * Cierra el modal de edición de escena y resetea las propiedades.
     */
    public function closeEditSceneModal(): void
    {
        $this->reset(['showEditSceneModal', 'editingSceneId', 'editingSceneSlugline', 'editingSceneLocationId']);
    }

    /**
     * Actualiza el encabezado canónico, momento del día y localización de la escena.
     */
    public function updateScene(?ManageScriptStructureAction $action = null): void
    {
        if (! $this->editingSceneId) {
            return;
        }

        $this->validate([
            'editingSceneSlugline' => 'required|min:3|max:120',
            'editingSceneTimeOfDay' => 'required',
        ]);

        $scene = $this->projectScene($this->editingSceneId);
        ($action ?? app(ManageScriptStructureAction::class))->updateScene($scene, [
            'slugline' => $this->editingSceneSlugline,
            'time_of_day' => $this->editingSceneTimeOfDay,
            'location_id' => $this->editingSceneLocationId,
        ]);

        // Sincronizar localizaciones del proyecto y notificar a la Biblia
        app(ScriptPipelineService::class)->syncProjectLocations($this->project);
        $this->dispatch('bible-updated');

        $this->logActivity('update_scene', 'Scene', $scene->id, "Escena #{$scene->scene_order} actualizada ({$scene->slugline})", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'slugline' => $scene->slugline,
            'time_of_day' => $scene->time_of_day,
            'location_id' => $scene->location_id,
        ]);

        $this->closeEditSceneModal();
        session()->flash('success', "Escena #{$scene->scene_order} actualizada correctamente.");
    }

    /**
     * Abre el modal de advertencia para eliminar un bloque de guión.
     */
    public function openDeleteSceneModal(string $sceneId): void
    {
        $scene = $this->projectScene($sceneId);
        $this->deletingSceneId = $scene->id;
        $this->deletingSceneOrder = $scene->scene_order;
        $this->deletingSceneSlugline = $scene->slugline;
        $this->deletingSceneBlockCount = $scene->scriptBlocks()->count();
        $this->showDeleteSceneModal = true;
    }

    /**
     * Cierra el modal de eliminación de escena.
     */
    public function closeDeleteSceneModal(): void
    {
        $this->reset(['showDeleteSceneModal', 'deletingSceneId', 'deletingSceneBlockCount', 'deletingSceneOrder', 'deletingSceneSlugline']);
    }

    /**
     * Confirma la eliminación irreversible de la escena (solo si no contiene bloques).
     */
    public function confirmDeleteScene(?ManageScriptStructureAction $action = null): void
    {
        if (! $this->deletingSceneId) {
            return;
        }

        $scene = $this->projectScene($this->deletingSceneId);
        $res = ($action ?? app(ManageScriptStructureAction::class))->deleteScene($scene);

        if (! $res['success']) {
            session()->flash('error', $res['message']);
            $this->closeDeleteSceneModal();

            return;
        }

        $this->logActivity('delete_scene', 'Scene', $scene->id, "Escena #{$scene->scene_order} ({$scene->slugline}) eliminada permanentemente", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'slugline' => $scene->slugline,
            'time_of_day' => $scene->time_of_day,
            'location_id' => $scene->location_id,
        ]);

        if ($this->activeSceneId === $scene->id) {
            $nextScene = $this->project->scenes()->where('id', '!=', $scene->id)->first();
            $this->activeSceneId = $nextScene?->id;
            $this->selectedBlockId = null;
        }

        $this->dispatch('bible-updated');

        $this->closeDeleteSceneModal();
        session()->flash('success', 'Escena eliminada correctamente y registrada en el historial.');
    }

    /**
     * Registra una acción del usuario en el historial de trazabilidad del proyecto.
     *
     * @param  array<string, mixed>|null  $snapshot
     */
    public function openEditTitleModal(): void
    {
        $this->projectTitle = $this->project->title;
        $this->projectSynopsis = (string) ($this->project->synopsis ?? '');
        $this->showEditTitleModal = true;
    }

    public function updateProjectTitle(): void
    {
        $this->validate([
            'projectTitle' => 'required|min:3|max:120',
            'projectSynopsis' => 'nullable|max:1000',
        ], [
            'projectTitle.required' => 'El título de la obra es obligatorio.',
            'projectTitle.min' => 'El título debe tener al menos 3 caracteres.',
            'projectTitle.max' => 'El título no puede superar los 120 caracteres.',
        ]);

        $this->project->update([
            'title' => $this->projectTitle,
            'synopsis' => $this->projectSynopsis,
        ]);

        $this->logActivity('update_project_title', 'Project', $this->project->id, "Título del proyecto modificado a \"{$this->projectTitle}\"");

        $this->showEditTitleModal = false;
        session()->flash('success', "Título del proyecto modificado a \"{$this->projectTitle}\".");
    }

    public function setPipelineStage(string $stage): void
    {
        $this->pipelineStage = $stage;
        $this->viewMode = match ($stage) {
            'idea' => 'all',
            'structured' => 'structured',
            'screenplay' => 'screenplay',
            'shots' => 'shots',
            'timeline' => 'timeline',
            default => 'all',
        };

        if ($this->selectedBlockId) {
            $this->dispatch('scroll-to-selected-block', blockId: $this->selectedBlockId);
        }
    }
}
