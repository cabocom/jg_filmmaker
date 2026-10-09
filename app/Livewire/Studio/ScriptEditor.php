<?php

namespace App\Livewire\Studio;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ScriptEditor extends Component
{
    use WithFileUploads;

    public Project $project;

    public ?string $activeSceneId = null;

    // Pestaña visual activa global para los bloques: 'all', 'raw', 'structured', 'screenplay', 'shots', 'timeline'
    public string $viewMode = 'all';

    // Subida de archivo de vídeo para toma
    public $videoUpload = null;

    public ?string $uploadingTakeId = null;

    // Edición de notas editoriales por toma
    public ?string $editingTakeNotesId = null;

    public string $editingTakeNotesText = '';

    // Estado de edición de un bloque específico
    public ?string $editingBlockId = null;

    public string $editRawText = '';

    public string $editStructuredText = '';

    public string $editScreenplayContent = '';

    public string $editStage = 'raw';

    // Formulario para nueva escena
    public bool $showNewSceneModal = false;

    public string $newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';

    public string $newSceneTimeOfDay = 'DÍA';

    public ?string $newSceneLocationId = null;

    // Formulario para nuevo plano técnico
    public bool $showNewShotModal = false;

    public string $newShotType = 'PM';

    public string $newShotDescription = '';

    public ?string $newShotBlockId = null;

    // Formulario para nueva toma
    public ?string $addingTakeShotId = null;

    public string $newTakeEditorialNotes = '';

    public bool $newTakeIsGood = true;

    public function mount(Project $project): void
    {
        $this->project = $project;

        // Cargar primera escena disponible o dejar en null
        $firstScene = $this->project->scenes()->first();
        if ($firstScene) {
            $this->activeSceneId = $firstScene->id;
        }
    }

    public function selectScene(string $sceneId): void
    {
        $this->activeSceneId = $sceneId;
        $this->editingBlockId = null;
    }

    public function createScene(): void
    {
        $this->validate([
            'newSceneSlugline' => 'required|min:3|max:120',
            'newSceneTimeOfDay' => 'required',
        ]);

        $maxOrder = $this->project->scenes()->max('scene_order') ?? 0;

        $scene = $this->project->scenes()->create([
            'scene_order' => $maxOrder + 1,
            'slugline' => strtoupper(trim($this->newSceneSlugline)),
            'time_of_day' => strtoupper(trim($this->newSceneTimeOfDay)),
            'location_id' => $this->newSceneLocationId ?: null,
        ]);

        // Crear bloque inicial vacío en la escena
        $scene->scriptBlocks()->create([
            'block_order' => 1,
            'raw_text' => 'Escribe aquí la acción o idea en bruto...',
            'stage' => 'raw',
            'user_locks' => [],
        ]);

        $this->activeSceneId = $scene->id;
        $this->reset(['showNewSceneModal', 'newSceneLocationId']);
        $this->newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';

        session()->flash('success', "Escena {$scene->scene_order} creada.");
    }

    public function addBlock(): void
    {
        if (! $this->activeSceneId) {
            return;
        }

        $scene = Scene::findOrFail($this->activeSceneId);
        $maxOrder = $scene->scriptBlocks()->max('block_order') ?? 0;

        $block = $scene->scriptBlocks()->create([
            'block_order' => $maxOrder + 1,
            'raw_text' => 'Nuevo párrafo o estímulo dramático...',
            'stage' => 'raw',
            'user_locks' => [],
        ]);

        $this->startEditBlock($block->id);
    }

    public function startEditBlock(string $blockId): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $this->editingBlockId = $block->id;
        $this->editRawText = $block->raw_text ?? '';
        $this->editStructuredText = $block->structured_text ?? '';
        $this->editScreenplayContent = $block->screenplay_content ?? '';
        $this->editStage = $block->stage ?? 'raw';
    }

    public function saveBlock(ScriptPipelineService $pipelineService): void
    {
        if (! $this->editingBlockId) {
            return;
        }

        $block = ScriptBlock::findOrFail($this->editingBlockId);

        // Retener modificaciones manuales en user_locks si el usuario editó etapas ya procesadas
        if (! empty($block->structured_text) && $this->editStructuredText !== $block->structured_text) {
            $pipelineService->recordManualOverride($block, 'structured', $block->structured_text, $this->editStructuredText);
        }

        if (! empty($block->screenplay_content) && $this->editScreenplayContent !== $block->screenplay_content) {
            $pipelineService->recordManualOverride($block, 'screenplay', $block->screenplay_content, $this->editScreenplayContent);
        }

        $block->update([
            'raw_text' => $this->editRawText,
            'structured_text' => $this->editStructuredText ?: null,
            'screenplay_content' => $this->editScreenplayContent ?: null,
            'stage' => $this->editStage,
        ]);

        $this->editingBlockId = null;
        session()->flash('success', "Bloque {$block->block_order} actualizado y bloqueos retenidos.");
    }

    public function structureBlock(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $pipelineService->transitionToStructured($block);

        if ($this->editingBlockId === $block->id) {
            $this->editStructuredText = (string) $block->fresh()->structured_text;
            $this->editStage = 'structured';
        }

        session()->flash('success', "Bloque {$block->block_order} estructurado a tratamiento cinematográfico.");
    }

    public function formatBlockToScreenplay(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $pipelineService->transitionToScreenplay($block);

        if ($this->editingBlockId === $block->id) {
            $this->editScreenplayContent = (string) $block->fresh()->screenplay_content;
            $this->editStage = 'screenplay';
        }

        session()->flash('success', "Bloque {$block->block_order} formateado a guion literario.");
    }

    public function breakdownBlockShots(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $createdShots = $pipelineService->generateShotsBreakdown($block);

        session()->flash('success', "Bloque {$block->block_order} desglosado: {$createdShots->count()} planos sincronizados.");
    }

    public function setBlockStage(string $blockId, string $stage): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $block->update(['stage' => $stage]);
    }

    public function toggleGoodTake(string $takeId): void
    {
        $take = Take::findOrFail($takeId);
        $take->update([
            'is_good_take' => ! $take->is_good_take,
        ]);
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

        $scene = Scene::findOrFail($this->activeSceneId);
        $maxNumber = $scene->shots()->max('shot_number') ?? 0;

        $shot = $scene->shots()->create([
            'shot_number' => $maxNumber + 1,
            'shot_type' => strtoupper(trim($this->newShotType)),
            'description' => $this->newShotDescription,
            'script_block_id' => $this->newShotBlockId ?: null,
        ]);

        // Crear una toma inicial por defecto
        $shot->takes()->create([
            'take_number' => 1,
            'is_good_take' => false,
            'editorial_notes' => 'Toma inicial',
            'technical_meta' => [
                'resolution' => '3840x2160',
                'codec' => 'prores422hq',
                'fps' => 24.0,
            ],
        ]);

        $this->reset(['showNewShotModal', 'newShotDescription', 'newShotBlockId']);
        session()->flash('success', "Plano {$shot->shot_number} ({$shot->shot_type}) añadido.");
    }

    public function saveNewTake(): void
    {
        if (! $this->addingTakeShotId) {
            return;
        }

        $shot = Shot::findOrFail($this->addingTakeShotId);
        $maxTakeNumber = $shot->takes()->max('take_number') ?? 0;

        $shot->takes()->create([
            'take_number' => $maxTakeNumber + 1,
            'is_good_take' => $this->newTakeIsGood,
            'editorial_notes' => $this->newTakeEditorialNotes ?: null,
            'technical_meta' => [
                'resolution' => '3840x2160',
                'codec' => 'prores422hq',
                'fps' => 24.0,
            ],
        ]);

        $this->reset(['addingTakeShotId', 'newTakeEditorialNotes', 'newTakeIsGood']);
        session()->flash('success', "Toma añadida al Plano {$shot->shot_number}.");
    }

    public function startUploadVideo(string $takeId): void
    {
        $this->uploadingTakeId = $takeId;
        $this->videoUpload = null;
    }

    public function uploadTakeVideo(): void
    {
        if (! $this->uploadingTakeId) {
            return;
        }

        $this->validate([
            'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|max:102400',
        ]);

        $take = Take::with('shot.scene')->findOrFail($this->uploadingTakeId);

        // Si ya existía un archivo previo, eliminarlo físicamente
        if ($take->file_path && Storage::disk('public')->exists($take->file_path)) {
            Storage::disk('public')->delete($take->file_path);
        }

        $extension = $this->videoUpload->getClientOriginalExtension() ?: 'mp4';
        $storagePath = $take->generateStoragePath($extension);

        $this->videoUpload->storeAs('', $storagePath, 'public');

        $currentMeta = $take->technical_meta ?? [];
        if (empty($currentMeta['duration_seconds'])) {
            $currentMeta['duration_seconds'] = 5.0;
        }
        if (empty($currentMeta['resolution'])) {
            $currentMeta['resolution'] = '1920x1080';
        }
        if (empty($currentMeta['fps'])) {
            $currentMeta['fps'] = 24.0;
        }
        if (empty($currentMeta['codec'])) {
            $currentMeta['codec'] = strtolower($extension) === 'mov' ? 'prores' : 'h264';
        }

        $take->update([
            'file_path' => $storagePath,
            'technical_meta' => $currentMeta,
        ]);

        $takeNumber = $take->take_number;
        $this->reset(['videoUpload', 'uploadingTakeId']);
        session()->flash('success', "Vídeo de la Toma {$takeNumber} subido correctamente.");
    }

    public function deleteTake(string $takeId): void
    {
        $take = Take::findOrFail($takeId);
        $shotNumber = $take->shot?->shot_number ?? 1;
        $takeNumber = $take->take_number;

        $take->delete();

        session()->flash('success', "Toma {$takeNumber} del Plano {$shotNumber} eliminada.");
    }

    public function startEditTakeNotes(string $takeId): void
    {
        $take = Take::findOrFail($takeId);
        $this->editingTakeNotesId = $take->id;
        $this->editingTakeNotesText = (string) ($take->editorial_notes ?? '');
    }

    public function saveTakeNotes(): void
    {
        if (! $this->editingTakeNotesId) {
            return;
        }

        $take = Take::findOrFail($this->editingTakeNotesId);
        $take->update([
            'editorial_notes' => $this->editingTakeNotesText ?: null,
        ]);

        $takeNumber = $take->take_number;
        $this->reset(['editingTakeNotesId', 'editingTakeNotesText']);
        session()->flash('success', "Notas editoriales de la Toma {$takeNumber} guardadas.");
    }

    public function render()
    {
        $scenes = $this->project->scenes()->with('location')->orderBy('scene_order')->get();

        $activeScene = null;
        if ($this->activeSceneId) {
            $activeScene = Scene::with([
                'location',
                'scriptBlocks',
                'shots.takes',
            ])->find($this->activeSceneId);
        }

        return view('livewire.studio.script-editor', [
            'scenes' => $scenes,
            'activeScene' => $activeScene,
            'locations' => $this->project->locations()->get(),
        ])->layoutData([
            'title' => $this->project->title.' — Estudio',
            'currentProject' => $this->project,
        ]);
    }
}
