<?php

namespace App\Livewire\Studio;

use App\Livewire\Studio\Concerns\WithEditorActivityAndTrace;
use App\Livewire\Studio\Concerns\WithEditorAiPipeline;
use App\Livewire\Studio\Concerns\WithEditorBlockCrud;
use App\Livewire\Studio\Concerns\WithEditorBlockDrawer;
use App\Livewire\Studio\Concerns\WithEditorImportExportBatch;
use App\Livewire\Studio\Concerns\WithEditorPlotHoles;
use App\Livewire\Studio\Concerns\WithEditorProperties;
use App\Livewire\Studio\Concerns\WithEditorSceneOperations;
use App\Livewire\Studio\Concerns\WithEditorScenePipeline;
use App\Livewire\Studio\Concerns\WithEditorShots;
use App\Livewire\Studio\Concerns\WithEditorStoryboard;
use App\Livewire\Studio\Concerns\WithEditorTakes;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ScriptEditor extends Component
{
    use WithEditorActivityAndTrace;
    use WithEditorAiPipeline;
    use WithEditorBlockCrud;
    use WithEditorBlockDrawer;
    use WithEditorImportExportBatch;
    use WithEditorPlotHoles;
    use WithEditorProperties;
    use WithEditorSceneOperations;
    use WithEditorScenePipeline;
    use WithEditorShots;
    use WithEditorStoryboard;
    use WithEditorTakes;
    use WithFileUploads;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
        $this->storyboardStyle = $this->project->storyboard_style ?: 'grayscale_sketch';

        // Cargar primera escena disponible o dejar en null (sin auto-seleccionar bloque)
        $firstScene = $this->project->scenes()->first();
        if ($firstScene) {
            $this->activeSceneId = $firstScene->id;
            $this->selectedBlockId = null;
        }

        // Por defecto, todas las escenas expandidas para lectura continua vertical estilo StoryArchitect
        $scenes = $this->project->scenes()->get();
        $this->expandedSceneIds = $scenes->pluck('id')->toArray();

        foreach ($scenes as $sc) {
            $this->scenePipelineStages[$sc->id] = 'idea';
        }
    }

    /**
     * Obtiene un bloque de guión verificando que pertenezca estrictamente a este proyecto.
     *
     * @param  array<int, string>  $with
     */
    public function render()
    {
        $scenes = $this->project->scenes()
            ->with([
                'location',
                'scriptBlocks' => fn ($q) => $q->orderBy('block_order'),
                'scriptBlocks.shots.takes',
                'shots.takes',
            ])
            ->orderBy('scene_order')
            ->get();

        $activeScene = $this->activeSceneId
            ? $scenes->firstWhere('id', $this->activeSceneId)
            : null;

        $activityLogs = $this->showActivityHistoryModal
            ? $this->project->activityLogs()
                ->select(['id', 'user_id', 'project_id', 'action', 'entity_type', 'entity_id', 'description', 'snapshot', 'created_at'])
                ->with('user:id,name,email')
                ->latest()
                ->take(50)
                ->get()
            : collect();

        return view('livewire.studio.script-editor', [
            'scenes' => $scenes,
            'activeScene' => $activeScene,
            'locations' => $this->project->locations()->get(),
            'activityLogs' => $activityLogs,
            'storyboardStyle' => $this->storyboardStyle,
            'previewStoryboardUrl' => $this->previewStoryboardUrl,
        ])->layoutData([
            'title' => $this->project->title.' — Estudio',
            'currentProject' => $this->project,
        ]);
    }
}
