<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageScriptStructureAction;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\ScriptPipelineService;
use Illuminate\Validation\Rule;

trait WithEditorSceneOperations
{
    use WithEditorSceneModals;

    protected function projectBlock(string $id, array $with = []): ScriptBlock
    {
        return ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))
            ->when(! empty($with), fn ($q) => $q->with($with))
            ->findOrFail($id);
    }

    /** Obtiene una escena verificando que pertenezca a este proyecto. */
    protected function projectScene(string $id): Scene
    {
        return $this->project->scenes()->findOrFail($id);
    }

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
        if ($this->newSceneLocationId === '') {
            $this->newSceneLocationId = null;
        }

        $this->validate([
            'newSceneSlugline' => 'required|min:3|max:120',
            'newSceneTimeOfDay' => 'required',
            'newSceneLocationId' => [
                'nullable',
                Rule::exists('locations', 'id')->where('project_id', $this->project->id),
            ],
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
