<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ManageTakeAction;
use App\Models\Scene;
use App\Models\Take;
use Illuminate\Support\Facades\DB;

trait WithEditorTakes
{
    protected function projectTake(string $id, array $with = []): Take
    {
        return Take::whereHas('shot.scene', fn ($q) => $q->where('project_id', $this->project->id))
            ->when(! empty($with), fn ($q) => $q->with($with))
            ->findOrFail($id);
    }

    public function toggleGoodTake(string $takeId, ?ManageTakeAction $action = null): void
    {
        $take = $this->projectTake($takeId);
        ($action ?? app(ManageTakeAction::class))->toggleGoodTake($take);
    }

    public function addShot(): void
    {
        if (! $this->activeSceneId) {
            return;
        }

        $this->validate([
            'newShotType' => 'required',
            'newShotDescription' => 'required|min:3',
        ]);

        $scene = $this->projectScene($this->activeSceneId);

        $shot = DB::transaction(function () use ($scene) {
            Scene::whereKey($scene->id)->lockForUpdate()->first();
            $maxNumber = $scene->shots()->max('shot_number') ?? 0;

            $newShot = $scene->shots()->create([
                'shot_number' => $maxNumber + 1,
                'shot_type' => strtoupper(trim($this->newShotType)),
                'description' => $this->newShotDescription,
                'script_block_id' => $this->newShotBlockId ?: null,
            ]);

            // Crear una toma inicial por defecto
            $newShot->takes()->create([
                'take_number' => 1,
                'is_good_take' => false,
                'editorial_notes' => 'Toma inicial',
                'technical_meta' => [
                    'resolution' => '3840x2160',
                    'codec' => 'prores422hq',
                    'fps' => 24.0,
                ],
            ]);

            return $newShot;
        });

        $this->reset(['showNewShotModal', 'newShotDescription', 'newShotBlockId']);
        session()->flash('success', "Plano {$shot->shot_number} ({$shot->shot_type}) añadido.");
    }

    public function saveNewTake(?ManageTakeAction $action = null): void
    {
        if (! $this->addingTakeShotId) {
            return;
        }

        $shot = $this->projectShot($this->addingTakeShotId);
        ($action ?? app(ManageTakeAction::class))->createTake($shot, $this->newTakeIsGood, $this->newTakeEditorialNotes);

        $this->reset(['addingTakeShotId', 'newTakeEditorialNotes', 'newTakeIsGood']);
        session()->flash('success', "Toma añadida al Plano {$shot->shot_number}.");
    }

    public function startUploadVideo(string $takeId): void
    {
        $this->uploadingTakeId = $takeId;
        $this->videoUpload = null;
    }

    public function uploadTakeVideo(?ManageTakeAction $action = null): void
    {
        if (! $this->uploadingTakeId) {
            return;
        }

        $this->validate([
            'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|extensions:mp4,mov,webm,m4v|max:102400',
        ]);

        $take = $this->projectTake($this->uploadingTakeId, ['shot.scene']);

        try {
            ($action ?? app(ManageTakeAction::class))->uploadVideo($take, $this->videoUpload);

            $takeNumber = $take->take_number;
            $this->reset(['videoUpload', 'uploadingTakeId']);
            session()->flash('success', "Vídeo de la Toma {$takeNumber} subido y analizado correctamente.");
        } catch (\Throwable $e) {
            $this->addError('videoUpload', $e->getMessage());
        }
    }

    public function deleteTake(string $takeId, ?ManageTakeAction $action = null): void
    {
        $take = $this->projectTake($takeId);
        $shotNumber = $take->shot?->shot_number ?? 1;
        $takeNumber = $take->take_number;

        ($action ?? app(ManageTakeAction::class))->deleteTake($take);

        session()->flash('success', "Toma {$takeNumber} del Plano {$shotNumber} eliminada.");
    }

    public function startEditTakeNotes(string $takeId): void
    {
        $take = $this->projectTake($takeId);
        $this->editingTakeNotesId = $take->id;
        $this->editingTakeNotesText = (string) ($take->editorial_notes ?? '');
    }

    public function saveTakeNotes(?ManageTakeAction $action = null): void
    {
        if (! $this->editingTakeNotesId) {
            return;
        }

        $this->validate([
            'editingTakeNotesText' => 'nullable|string|max:5000',
        ]);

        $take = $this->projectTake($this->editingTakeNotesId);
        ($action ?? app(ManageTakeAction::class))->saveNotes($take, $this->editingTakeNotesText);

        $takeNumber = $take->take_number;
        $this->reset(['editingTakeNotesId', 'editingTakeNotesText']);
        session()->flash('success', "Notas editoriales de la Toma {$takeNumber} guardadas.");
    }

    /**
     * Audita y detecta agujeros de guión pasando el análisis a cada consolidado por separado (Idea, Tratamiento, Guion).
     * Si un bloque presenta inconsistencias en varias etapas, se marca como agujero el del paso más temprano.
     */
}
