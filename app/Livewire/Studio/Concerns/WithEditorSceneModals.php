<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageScriptStructureAction;
use App\Services\ScriptPipelineService;
use Illuminate\Validation\Rule;

trait WithEditorSceneModals
{
    /** Abre el modal para editar los datos canónicos de una escena. */
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

    /** Cierra el modal de edición de escena y resetea las propiedades. */
    public function closeEditSceneModal(): void
    {
        $this->reset(['showEditSceneModal', 'editingSceneId', 'editingSceneSlugline', 'editingSceneLocationId']);
    }

    /** Actualiza el encabezado canónico, momento del día y localización de la escena. */
    public function updateScene(?ManageScriptStructureAction $action = null): void
    {
        if (! $this->editingSceneId) {
            return;
        }

        if ($this->editingSceneLocationId === '') {
            $this->editingSceneLocationId = null;
        }

        $this->validate([
            'editingSceneSlugline' => 'required|min:3|max:120',
            'editingSceneTimeOfDay' => 'required',
            'editingSceneLocationId' => [
                'nullable',
                Rule::exists('locations', 'id')->where('project_id', $this->project->id),
            ],
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

    /** Abre el modal de advertencia para eliminar una escena. */
    public function openDeleteSceneModal(string $sceneId): void
    {
        $scene = $this->projectScene($sceneId);
        $this->deletingSceneId = $scene->id;
        $this->deletingSceneOrder = $scene->scene_order;
        $this->deletingSceneSlugline = $scene->slugline;
        $this->deletingSceneBlockCount = $scene->scriptBlocks()->count();
        $this->showDeleteSceneModal = true;
    }

    /** Cierra el modal de eliminación de escena. */
    public function closeDeleteSceneModal(): void
    {
        $this->reset(['showDeleteSceneModal', 'deletingSceneId', 'deletingSceneBlockCount', 'deletingSceneOrder', 'deletingSceneSlugline']);
    }

    /** Confirma la eliminación irreversible de la escena (solo si no contiene bloques). */
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

    /** Abre el modal para editar el título y sinopsis del proyecto. */
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
}
