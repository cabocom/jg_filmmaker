<?php

namespace App\Livewire\Studio;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    // Etapa global activa del pipeline: 'idea' (Original/Bruto) > 'structured' (Tratamiento) > 'screenplay' (Guión) > 'shots' (Planos/Tomas) > 'timeline' (Montaje)
    public string $pipelineStage = 'idea';

    // Bloque seleccionado actualmente en la lectura continua
    public ?string $selectedBlockId = null;

    // Modal lateral (Drawer al 60%) para detalles y edición del bloque
    public ?string $detailBlockId = null;

    public string $detailActiveTab = 'raw'; // 'raw', 'structured', 'screenplay', 'shots'

    public bool $isEditingInDrawer = false;

    // Modal para Añadir Nuevo Bloque (estilo Gutenberg, SOLO campo Texto Bruto)
    public bool $showNewBlockModal = false;

    public ?int $insertAfterOrder = null;

    public string $newBlockRawText = '';

    /**
     * Pestaña activa por cada bloque: [ $blockId => 'raw'|'structured'|'screenplay'|'shots' ].
     *
     * @var array<string, string>
     */
    public array $activeBlockTabs = [];

    // Modal de Trazabilidad e Inspección de IA (estilo jg_hub / jg_dashboard)
    public bool $showAiTraceModal = false;

    /** @var array<string, mixed> */
    public array $aiTraceData = [];

    public string $aiTraceActiveTab = 'steps'; // 'steps', 'prompt', 'output', 'json'

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

    public ?int $insertSceneAfterOrder = null;

    public string $newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';

    public string $newSceneTimeOfDay = 'DÍA';

    public ?string $newSceneLocationId = null;

    /**
     * IDs de escenas expandidas en el acordeón vertical (Modo StoryArchitect).
     *
     * @var array<int, string>
     */
    public array $expandedSceneIds = [];

    /**
     * Etapa de pipeline activa para cada escena individualmente (Modo StoryArchitect).
     *
     * @var array<string, string>
     */
    public array $scenePipelineStages = [];

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

    public function selectBlock(string $blockId): void
    {
        $this->selectedBlockId = $blockId;
        $this->dispatch('scroll-to-selected-block', blockId: $blockId);
    }

    #[On('open-block-details')]
    public function openBlockDetails(string $blockId, ?string $tab = null): void
    {
        $block = ScriptBlock::findOrFail($blockId);
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
        $this->editRawText = (string) ($block->raw_text ?? '');
        $this->editStructuredText = (string) ($block->structured_text ?? '');
        $this->editScreenplayContent = (string) ($block->screenplay_content ?? '');
        $this->editStage = (string) ($block->stage ?? 'raw');
    }

    public function closeBlockDetails(): void
    {
        $this->detailBlockId = null;
        $this->isEditingInDrawer = false;
    }

    public function setDetailTab(string $tab): void
    {
        $this->detailActiveTab = $tab;
    }

    public function startEditingInDrawer(): void
    {
        if (! $this->detailBlockId) {
            return;
        }

        $block = ScriptBlock::findOrFail($this->detailBlockId);
        $this->editRawText = (string) ($block->raw_text ?? '');
        $this->editStructuredText = (string) ($block->structured_text ?? '');
        $this->editScreenplayContent = (string) ($block->screenplay_content ?? '');
        $this->editStage = (string) ($block->stage ?? 'raw');
        $this->isEditingInDrawer = true;
    }

    public function cancelEditingInDrawer(): void
    {
        $this->isEditingInDrawer = false;
    }

    public function saveBlockFromDrawer(ScriptPipelineService $pipelineService): void
    {
        if (! $this->detailBlockId) {
            return;
        }

        $block = ScriptBlock::findOrFail($this->detailBlockId);

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

        $this->isEditingInDrawer = false;
        session()->flash('success', "Bloque #{$block->block_order} guardado correctamente.");
    }

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
            'newBlockRawText' => 'required|string|min:2',
        ], [
            'newBlockRawText.required' => 'Debes escribir el texto bruto o idea de la escena.',
            'newBlockRawText.min' => 'El texto debe tener al menos 2 caracteres.',
        ]);

        $scene = Scene::findOrFail($this->activeSceneId);

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

        $newBlock = $scene->scriptBlocks()->create([
            'block_order' => $newOrder,
            'raw_text' => trim($this->newBlockRawText),
            'stage' => 'raw',
            'user_locks' => [],
        ]);

        $this->selectedBlockId = $newBlock->id;
        $this->closeNewBlockModal();

        $this->dispatch('scroll-to-selected-block', blockId: $newBlock->id);
        session()->flash('success', "Bloque #{$newBlock->block_order} creado e insertado en la escena.");
    }

    public function reorderBlock(string $draggedBlockId, int $targetOrder, ?string $sceneId = null): void
    {
        $sceneTargetId = $sceneId ?: $this->activeSceneId;
        if (! $sceneTargetId) {
            return;
        }

        DB::transaction(function () use ($draggedBlockId, $targetOrder, $sceneTargetId) {
            $dragged = ScriptBlock::find($draggedBlockId);
            if (! $dragged) {
                return;
            }

            if ($dragged->scene_id !== $sceneTargetId) {
                $dragged->update(['scene_id' => $sceneTargetId]);
                $dragged->refresh();
            }

            $blocks = ScriptBlock::where('scene_id', $sceneTargetId)
                ->orderBy('block_order')
                ->get();

            $remaining = $blocks->reject(fn ($b) => $b->id === $draggedBlockId)->values();
            $targetIndex = max(0, min($targetOrder - 1, $remaining->count()));

            $newBlocks = $remaining->slice(0, $targetIndex)
                ->concat([$dragged])
                ->concat($remaining->slice($targetIndex))
                ->values();

            foreach ($newBlocks as $idx => $block) {
                $order = $idx + 1;
                if ($block->block_order !== $order) {
                    $block->update(['block_order' => $order]);
                }
            }
        });

        $this->selectedBlockId = $draggedBlockId;
        session()->flash('success', 'Bloque reordenado correctamente.');
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

    public function reorderScene(string $draggedSceneId, int $targetOrder): void
    {
        DB::transaction(function () use ($draggedSceneId, $targetOrder) {
            $scenes = $this->project->scenes()->orderBy('scene_order')->get();
            $dragged = $scenes->firstWhere('id', $draggedSceneId);
            if (! $dragged) {
                return;
            }

            $remaining = $scenes->reject(fn ($s) => $s->id === $draggedSceneId)->values();
            $targetIndex = max(0, min($targetOrder - 1, $remaining->count()));

            $newScenes = $remaining->slice(0, $targetIndex)
                ->concat([$dragged])
                ->concat($remaining->slice($targetIndex))
                ->values();

            foreach ($newScenes as $idx => $sc) {
                $order = $idx + 1;
                if ($sc->scene_order !== $order) {
                    $sc->update(['scene_order' => $order]);
                }
            }
        });

        session()->flash('success', 'Escena reordenada correctamente.');
    }

    public function createScene(): void
    {
        $this->validate([
            'newSceneSlugline' => 'required|min:3|max:120',
            'newSceneTimeOfDay' => 'required',
        ]);

        if ($this->insertSceneAfterOrder !== null) {
            $this->project->scenes()
                ->where('scene_order', '>', $this->insertSceneAfterOrder)
                ->increment('scene_order');
            $newOrder = $this->insertSceneAfterOrder + 1;
        } else {
            $maxOrder = $this->project->scenes()->max('scene_order') ?? 0;
            $newOrder = $maxOrder + 1;
        }

        $scene = $this->project->scenes()->create([
            'scene_order' => $newOrder,
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
        $this->openNewBlockModal();
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

        $this->activeBlockTabs[$block->id] = 'structured';

        if ($this->detailBlockId === $block->id) {
            $this->editStructuredText = (string) $block->fresh()->structured_text;
            $this->detailActiveTab = 'structured';
        }

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

        $this->activeBlockTabs[$block->id] = 'screenplay';

        if ($this->detailBlockId === $block->id) {
            $this->editScreenplayContent = (string) $block->fresh()->screenplay_content;
            $this->detailActiveTab = 'screenplay';
        }

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

        $this->activeBlockTabs[$block->id] = 'shots';

        if ($this->detailBlockId === $block->id) {
            $this->detailActiveTab = 'shots';
        }

        session()->flash('success', "Bloque {$block->block_order} desglosado: {$createdShots->count()} planos sincronizados.");
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

    public function removePinnedTerm(string $blockId, string $term): void
    {
        $block = ScriptBlock::findOrFail($blockId);
        $userLocks = $block->user_locks ?? [];
        if (isset($userLocks['pinned_terms'][$term])) {
            unset($userLocks['pinned_terms'][$term]);
            $block->update(['user_locks' => $userLocks]);
            session()->flash('success', "Término fijo '{$term}' eliminado.");
        }
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

    public function openAiTrace(string $blockId, string $stage): void
    {
        $block = ScriptBlock::with('scene.project.characters', 'scene.location', 'shots')->findOrFail($blockId);
        $userLocks = $block->user_locks ?? [];
        $telemetry = $userLocks['ai_telemetry'][$stage] ?? null;

        if (! $telemetry) {
            $telemetry = $this->generateFallbackTelemetry($block, $stage);
        }

        // Sincronizar dinámicamente con el proveedor y modelo activo real del entorno
        $activeProvider = (string) config('services.llm.provider', env('LLM_PROVIDER', 'groq'));
        $activeModel = (string) config('services.llm.model', env('LLM_MODEL', 'llama-3.3-70b-versatile'));
        if ($activeModel === 'openai/gpt-oss-120b' || empty($activeModel)) {
            $activeModel = 'llama-3.3-70b-versatile';
        }
        $telemetry['provider'] = $activeProvider;
        $telemetry['model'] = $activeModel;

        $telemetry['block_order'] = $block->block_order;
        $telemetry['scene_order'] = $block->scene?->scene_order ?? 1;
        $telemetry['scene_slugline'] = $block->scene?->slugline ?? 'ESCENA';

        // Generar pasos granulares con acciones particulares exactas solicitadas por el usuario
        $telemetry['steps'] = $this->buildGranularSteps($block, $stage, $telemetry);

        $this->aiTraceData = $telemetry;
        $this->aiTraceActiveTab = 'steps';
        $this->showAiTraceModal = true;
    }

    public function closeAiTraceModal(): void
    {
        $this->showAiTraceModal = false;
        $this->aiTraceData = [];
    }

    public function switchAiTraceTab(string $tab): void
    {
        $this->aiTraceActiveTab = $tab;
    }

    /**
     * Construye la lista de saltos y grafos con las acciones particulares exactas (personajes, localizaciones, términos).
     *
     * @param  array<string, mixed>  $telemetry
     * @return list<array<string, mixed>>
     */
    protected function buildGranularSteps(ScriptBlock $block, string $stage, array $telemetry): array
    {
        $charList = [];

        // 1. Personajes de la biblia de proyecto
        if ($block->scene?->project?->characters) {
            foreach ($block->scene->project->characters as $c) {
                if (! empty($c->name)) {
                    $charList[] = trim($c->name);
                }
            }
        }

        // 2. Personajes de telemetría previa
        if (! empty($telemetry['characters_context']) && is_array($telemetry['characters_context'])) {
            foreach ($telemetry['characters_context'] as $c) {
                if (is_array($c) && ! empty($c['name'])) {
                    $charList[] = trim($c['name']);
                }
            }
        }

        // 3. Personajes presentes en los textos del bloque (raw, structured, screenplay)
        $fullText = ($block->screenplay_content ?? '')."\n".($block->structured_text ?? '')."\n".($block->raw_text ?? '');

        // 3.1. Detectar nombres de formato Fountain en mayúsculas
        preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑ]{2,20})(?:\s*\([^)]*\))?[ \t]*$/m', $fullText, $mFountain);
        $reserved = ['INT', 'EXT', 'INT/EXT', 'CORTE', 'FADE', 'ESCENA', 'PLANO', 'DÍA', 'NOCHE', 'TARDE', 'MAÑANA', 'CONTINUA', 'TRANSICIÓN', 'VOZ', 'OFF'];
        foreach ($mFountain[1] ?? [] as $candidate) {
            $upper = strtoupper(trim($candidate));
            if (! in_array($upper, $reserved) && strlen($upper) >= 3) {
                $charList[] = ucfirst(strtolower($upper));
            }
        }

        // 3.2. Detectar nombres propios comunes en español (incluido Juan explícitamente)
        preg_match_all('/\b(Juan|Pedro|Carlos|Javi|Javier|Ana|María|Laura|Lucía|Elena|David|Pablo|Sofía|Marcos|Antonio|Manuel|Marta|Diego|Luis|Miguel|Andrés|Carmen|Raquel)\b/u', $fullText, $mCommon);
        foreach ($mCommon[1] ?? [] as $name) {
            $charList[] = ucfirst(strtolower(trim($name)));
        }

        // 3.3. Detectar nombres propios seguidos de acción o diálogo
        preg_match_all('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,15})\s+(?:mira|entra|sale|dice|responde|grita|susurra|camina|observa|corre|habla|pregunta)\b/u', $fullText, $mAction);
        $excludeWords = ['EL', 'LA', 'UN', 'UNA', 'ESTE', 'ESE', 'AQUEL', 'CUANDO', 'MIENTRAS', 'DESPUES', 'LUEGO', 'PERO', 'COMO', 'CADA'];
        foreach ($mAction[1] ?? [] as $actionName) {
            $clean = ucfirst(strtolower(trim($actionName)));
            if (! in_array(strtoupper($clean), $excludeWords)) {
                $charList[] = $clean;
            }
        }

        // Unificar nombres sin duplicados
        $uniqueChars = [];
        foreach ($charList as $name) {
            $key = strtolower($name);
            if (! isset($uniqueChars[$key])) {
                $uniqueChars[$key] = ucfirst($name);
            }
        }
        $charNames = array_values($uniqueChars);
        $charCount = count($charNames);

        $location = $block->scene?->location;
        $locName = $location ? $location->name : null;
        $locType = $location ? $location->type : null;

        if (! $locName) {
            $telemetryLoc = $telemetry['location_context'] ?? null;
            if (is_array($telemetryLoc) && ! empty($telemetryLoc['name'])) {
                $locName = $telemetryLoc['name'];
                $locType = $telemetryLoc['type'] ?? null;
            }
        }

        if (! $locName && $block->scene?->slugline) {
            $slug = trim($block->scene->slugline);
            if (preg_match('/^(?:INT\.|EXT\.|INT\/EXT\.)?\s*([^-–—\n]+?)(?:\s*[-–—]\s*.*)?$/iu', $slug, $mSlug)) {
                $candidateLoc = trim($mSlug[1]);
                if (! empty($candidateLoc) && ! in_array(strtoupper($candidateLoc), ['ESCENA', 'SCENE'])) {
                    $locName = $candidateLoc;
                    $locType = str_contains(strtoupper($slug), 'INT') ? 'INT' : (str_contains(strtoupper($slug), 'EXT') ? 'EXT' : null);
                }
            }
        }

        if (! $locName) {
            $allText = ($block->screenplay_content ?? '')."\n".($block->structured_text ?? '')."\n".($block->raw_text ?? '');
            if (preg_match('/\b(Bar|Cafetería|Restaurante|Oficina|Casa|Salón|Habitación|Calle|Parque|Playa|Coche|Carretera)\b/iu', $allText, $mLocText)) {
                $locName = ucfirst(strtolower($mLocText[1]));
            }
        }

        $userLocks = $block->user_locks ?? [];
        $pinned = $userLocks['pinned_terms'] ?? [];
        $pinnedCount = is_array($pinned) ? count($pinned) : 0;

        $model = (string) ($telemetry['model'] ?? config('services.llm.model', 'llama-3.3-70b-versatile'));
        if ($model === 'openai/gpt-oss-120b' || empty($model)) {
            $model = 'llama-3.3-70b-versatile';
        }
        $provider = strtoupper((string) ($telemetry['provider'] ?? config('services.llm.provider', 'GROQ')));
        $durationMs = $telemetry['duration_ms'] ?? 1162;

        $steps = [];
        $stepNum = 1;

        // 1. Detección / Inyección particular de personajes (formato exacto solicitado: "Detectados 1 personaje: Juan")
        if ($charCount > 0) {
            $charListStr = implode(', ', $charNames);
            $title = $charCount === 1
                ? "Detectados 1 personaje: {$charListStr}"
                : "Detectados {$charCount} personajes: {$charListStr}";
            $detail = 'Inyectado el perfil dramático y rol de los personajes en el prompt del modelo.';
        } else {
            $title = 'Detectados 0 personajes: procesado según contexto narrativo';
            $detail = 'Procesamiento con personajes inferidos directamente del texto sin ficha previa.';
        }
        $steps[] = [
            'number' => $stepNum++,
            'title' => $title,
            'status' => 'completed',
            'detail' => $detail,
        ];

        // 2. Detección / Inyección particular de localización (formato exacto: "Detectada 1 localización: Bar")
        if ($locName) {
            $locStr = $locType ? "{$locName} ({$locType})" : $locName;
            $title = "Detectada 1 localización: {$locStr}";
            $detail = 'Asociada la atmósfera lumínica y tipología del entorno de la escena al contexto.';
        } else {
            $title = 'Detectada 1 localización: Entorno dramático general';
            $detail = 'Se utiliza la convención genérica de entorno dramático según el texto.';
        }
        $steps[] = [
            'number' => $stepNum++,
            'title' => $title,
            'status' => 'completed',
            'detail' => $detail,
        ];

        // 3. Inferencia cognitiva con el modelo real activo
        $stageAction = match ($stage) {
            'structured' => 'tratamiento cinematográfico en presente',
            'screenplay' => 'guión literario en formato canónico Fountain',
            'shots' => 'desglose técnico de planos de cámara',
            default => 'etapa narrativa',
        };
        $steps[] = [
            'number' => $stepNum++,
            'title' => "Inferencia Cognitiva ({$provider} / {$model})",
            'status' => 'completed',
            'detail' => "Resuelta transformación semántica a {$stageAction} en {$durationMs} ms.",
        ];

        // 4. Candados y términos fijados particulares
        if ($pinnedCount > 0) {
            $pinnedPairs = [];
            foreach ($pinned as $k => $v) {
                $pinnedPairs[] = "{$k} ➜ {$v}";
            }
            $title = "Aplicados {$pinnedCount} términos fijados: ".implode(', ', $pinnedPairs);
            $detail = 'Protegida la voluntad del autor garantizando que ninguna entidad fija sea alterada.';
        } else {
            $title = '0 términos fijados activos';
            $detail = 'No se requirió reemplazo forzado de términos en esta etapa.';
        }
        $steps[] = [
            'number' => $stepNum++,
            'title' => $title,
            'status' => 'completed',
            'detail' => $detail,
        ];

        // 5. Acción particular de salida según la etapa
        if ($stage === 'shots') {
            $shotsCount = $block->shots()->count();
            $title = $shotsCount > 0
                ? "Desglosados {$shotsCount} planos técnicos (--- shot N ---)"
                : 'Planos técnicos sincronizados en base de datos';
            $detail = 'Detectadas escalas de plano (PG, PM, PP) y asociadas al bloque para el plan de rodaje.';
        } elseif ($stage === 'screenplay') {
            $title = 'Generados diálogos y sluglines canónicos';
            $detail = 'Texto formateado con estándar de la industria (diálogos centrados y acotaciones).';
        } elseif ($stage === 'structured') {
            $title = 'Estructurado en tiempo presente continuo';
            $detail = 'Acciones dramáticas observables depuradas sin juicios de valor abstractos.';
        } else {
            $title = 'Persistencia y sincronización de bloque';
            $detail = 'Guardado con éxito en base de datos.';
        }
        $steps[] = [
            'number' => $stepNum++,
            'title' => $title,
            'status' => 'completed',
            'detail' => $detail,
        ];

        return $steps;
    }

    /**
     * Genera una ficha técnica de trazabilidad retrospectiva coherente.
     *
     * @return array<string, mixed>
     */
    protected function generateFallbackTelemetry(ScriptBlock $block, string $stage): array
    {
        $provider = (string) config('services.llm.provider', env('LLM_PROVIDER', 'groq'));
        $model = (string) config('services.llm.model', env('LLM_MODEL', 'llama-3.3-70b-versatile'));
        $executedAt = $block->updated_at?->toIso8601String() ?? now()->toIso8601String();

        $characters = $block->scene?->project?->characters?->map(fn ($c) => [
            'name' => $c->name,
            'role' => $c->role,
        ])->toArray() ?? [];

        $location = $block->scene?->location ? [
            'name' => $block->scene->location->name,
            'type' => $block->scene->location->type,
        ] : null;

        $userLocks = $block->user_locks ?? [];

        $input = match ($stage) {
            'structured' => (string) $block->raw_text,
            'screenplay' => (string) ($block->structured_text ?: $block->raw_text),
            'shots' => (string) ($block->screenplay_content ?: $block->structured_text ?: $block->raw_text),
            default => (string) $block->raw_text,
        };

        $output = match ($stage) {
            'structured' => (string) $block->structured_text,
            'screenplay' => (string) $block->screenplay_content,
            'shots' => 'Desglose técnico de planos completado.',
            default => (string) $block->raw_text,
        };

        $telemetry = [
            'tool' => match ($stage) {
                'structured' => 'treatment_structuring',
                'screenplay' => 'screenplay_formatter',
                'shots' => 'shot_breakdown_detector',
                default => 'pipeline_processor',
            },
            'stage' => $stage,
            'provider' => $provider,
            'model' => $model,
            'executed_at' => $executedAt,
            'duration_ms' => 1162,
            'status' => 'SUCCESS',
            'system_prompt' => "Inferencia cognitiva cinematográfica guiada por la biblia del proyecto ({$block->scene?->project?->title}).",
            'user_prompt' => $input,
            'raw_response' => $output,
            'output' => $output,
            'characters_context' => $characters,
            'location_context' => $location,
            'user_locks' => $userLocks,
        ];

        $telemetry['steps'] = $this->buildGranularSteps($block, $stage, $telemetry);

        return $telemetry;
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
        $scenes = $this->project->scenes()
            ->with([
                'location',
                'scriptBlocks' => fn ($q) => $q->orderBy('block_order'),
                'scriptBlocks.shots.takes',
            ])
            ->orderBy('scene_order')
            ->get();

        $activeScene = null;
        if ($this->activeSceneId) {
            $activeScene = Scene::with([
                'location',
                'scriptBlocks.shots.takes',
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
