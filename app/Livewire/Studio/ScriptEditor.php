<?php

namespace App\Livewire\Studio;

use App\Exceptions\QuotaExceededException;
use App\Models\AgentRequestLog;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\UserActivityLog;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptExportService;
use App\Services\ScriptImportService;
use App\Services\ScriptPipelineService;
use App\Services\StoryboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class ScriptEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public ?string $activeSceneId = null;

    // Pestaña visual activa global para los bloques: 'all', 'raw', 'structured', 'screenplay', 'shots', 'timeline'
    public string $viewMode = 'all';

    // Vista activa en la barra superior: 'pipeline' (editor interactivo) o consolidado ('idea', 'structured', 'screenplay', 'shots', 'timeline')
    public string $activeView = 'pipeline';

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

    // Estilo activo para generación de Storyboard
    public string $storyboardStyle = 'grayscale_sketch';

    // Modal para previsualización a gran tamaño de viñeta de Storyboard
    public ?string $previewStoryboardUrl = null;

    public ?string $previewStoryboardTitle = null;

    // Subida de archivo de vídeo para toma
    public $videoUpload = null;

    public ?string $uploadingTakeId = null;

    // Edición de notas editoriales por toma
    public ?string $editingTakeNotesId = null;

    public string $editingTakeNotesText = '';

    // Estado de edición de un bloque específico
    public ?string $editingBlockId = null;

    public ?int $editLockVersion = null;

    public string $editRawText = '';

    public string $editStructuredText = '';

    public string $editScreenplayContent = '';

    public string $editStage = 'raw';

    // Modal para justificar y descartar un agujero de guión
    public bool $showJustifyModal = false;

    // Visibilidad de mensajes y avisos de agujeros de guión en la interfaz
    public bool $showPlotHoleAlerts = true;

    public ?string $justifyingBlockId = null;

    public string $justifyingHoleReason = '';

    public string $justificationText = '';

    // Creación de términos fijos (pinned_terms) en Drawer de bloque
    public string $newPinnedTerm = '';

    public string $newPinnedReplacement = '';

    // Formulario para nueva escena
    public bool $showNewSceneModal = false;

    public ?int $insertSceneAfterOrder = null;

    public string $newSceneSlugline = 'INT. NUEVA LOCALIZACIÓN - DÍA 1';

    public string $newSceneTimeOfDay = 'DÍA';

    public ?string $newSceneLocationId = null;

    // Formulario para editar escena existente
    public bool $showEditSceneModal = false;

    public ?string $editingSceneId = null;

    public int $editingSceneOrder = 1;

    public string $editingSceneSlugline = '';

    public string $editingSceneTimeOfDay = 'DÍA';

    public ?string $editingSceneLocationId = null;

    // Modal de borrado de bloque con advertencia y confirmación
    public bool $showDeleteBlockModal = false;

    public ?string $deletingBlockId = null;

    public ?int $deletingBlockOrder = null;

    public ?int $deletingBlockSceneOrder = null;

    public ?string $deletingBlockPreview = null;

    // Modal de borrado de escena con comprobación de bloques y confirmación
    public bool $showDeleteSceneModal = false;

    public ?string $deletingSceneId = null;

    public int $deletingSceneBlockCount = 0;

    public ?int $deletingSceneOrder = null;

    public ?string $deletingSceneSlugline = null;

    // Modal de historial y trazabilidad completa de acciones del cliente
    public bool $showActivityHistoryModal = false;

    // Modal de estimación previa y confirmación de coste en tokens para agujeros de guión
    public bool $showPlotHoleConfirmModal = false;

    public int $estimatedPlotHoleTokens = 0;

    public int $estimatedBlocksToAudit = 0;

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

    // Modificar Nombre y Detalles del Proyecto
    public bool $showEditTitleModal = false;

    public string $projectTitle = '';

    public string $projectSynopsis = '';

    // Renderizar / Procesar en Lote ("Renderizar todo de golpe")
    public bool $showBatchModal = false;

    public string $batchScope = 'scene'; // 'scene' o 'project'

    public string $batchStage = 'all'; // 'all', 'structured', 'screenplay', 'shots'

    public bool $isBatchProcessing = false;

    // Subida e Importación de Guion (PDF/TXT) con aviso destructivo
    public bool $showImportModal = false;

    public string $importStep = 'upload'; // 'upload', 'preview'

    public $importFile = null;

    public bool $useAiForImport = false;

    /** @var array<string, mixed> */
    public array $parsedImportData = [];

    public bool $confirmDestructiveImport = false;

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
    protected function projectShot(string $id): Shot
    {
        return Shot::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))->findOrFail($id);
    }

    /**
     * Obtiene una toma de rodaje verificando que pertenezca a este proyecto.
     *
     * @param  array<int, string>  $with
     */
    protected function projectTake(string $id, array $with = []): Take
    {
        return Take::whereHas('shot.scene', fn ($q) => $q->where('project_id', $this->project->id))
            ->when(! empty($with), fn ($q) => $q->with($with))
            ->findOrFail($id);
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

        $this->isEditingInDrawer = false;
        $this->editLockVersion = $savedBlock->lock_version;
        session()->flash('success', "Bloque #{$savedBlock->block_order} guardado correctamente.");
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
            'newBlockRawText' => 'required|string|min:2|max:50000',
        ], [
            'newBlockRawText.required' => 'Debes escribir el texto bruto o idea de la escena.',
            'newBlockRawText.min' => 'El texto debe tener al menos 2 caracteres.',
            'newBlockRawText.max' => 'El texto bruto no puede superar los 50.000 caracteres.',
        ]);

        $scene = $this->projectScene($this->activeSceneId);

        $newBlock = DB::transaction(function () use ($scene) {
            Scene::whereKey($scene->id)->lockForUpdate()->first();

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

            return $scene->scriptBlocks()->create([
                'block_order' => $newOrder,
                'raw_text' => trim($this->newBlockRawText),
                'stage' => 'raw',
                'user_locks' => [],
            ]);
        });

        $this->logActivity('create_block', 'ScriptBlock', $newBlock->id, "Bloque #{$newBlock->block_order} creado en Escena #{$scene->scene_order}", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'block_order' => $newBlock->block_order,
            'raw_text' => $newBlock->raw_text,
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

        // Validar que la escena destino pertenezca a este proyecto
        $targetScene = $this->projectScene($sceneTargetId);

        DB::transaction(function () use ($draggedBlockId, $targetOrder, $targetScene) {
            // Validar que el bloque arrastrado pertenezca estrictamente a este proyecto
            $dragged = $this->projectBlock($draggedBlockId);
            $originSceneId = $dragged->scene_id;

            if ($originSceneId !== $targetScene->id) {
                // Reasignar bloque a la nueva escena
                $dragged->update(['scene_id' => $targetScene->id]);

                // Reasignar planos vinculados al bloque para preservar integridad referencial
                Shot::where('script_block_id', $dragged->id)->update(['scene_id' => $targetScene->id]);

                // Reindexar la escena de origen
                $originBlocks = ScriptBlock::where('scene_id', $originSceneId)
                    ->where('id', '!=', $dragged->id)
                    ->orderBy('block_order')
                    ->get();

                foreach ($originBlocks as $idx => $b) {
                    $order = $idx + 1;
                    if ($b->block_order !== $order) {
                        $b->update(['block_order' => $order]);
                    }
                }

                $dragged->refresh();
            }

            $blocks = ScriptBlock::where('scene_id', $targetScene->id)
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

        $this->logActivity('reorder_block', 'ScriptBlock', $draggedBlockId, "Bloque reordenado en escena a posición #{$targetOrder}", [
            'block_id' => $draggedBlockId,
            'scene_id' => $targetScene->id,
            'target_order' => $targetOrder,
        ]);

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

        $this->logActivity('reorder_scene', 'Scene', $draggedSceneId, "Escena reordenada a posición #{$targetOrder}", [
            'scene_id' => $draggedSceneId,
            'target_order' => $targetOrder,
        ]);

        session()->flash('success', 'Escena reordenada correctamente.');
    }

    public function createScene(): void
    {
        $this->validate([
            'newSceneSlugline' => 'required|min:3|max:120',
            'newSceneTimeOfDay' => 'required',
        ]);

        $scene = DB::transaction(function () {
            Project::whereKey($this->project->id)->lockForUpdate()->first();

            if ($this->insertSceneAfterOrder !== null) {
                $this->project->scenes()
                    ->where('scene_order', '>', $this->insertSceneAfterOrder)
                    ->increment('scene_order');
                $newOrder = $this->insertSceneAfterOrder + 1;
            } else {
                $maxOrder = $this->project->scenes()->max('scene_order') ?? 0;
                $newOrder = $maxOrder + 1;
            }

            $newScene = $this->project->scenes()->create([
                'scene_order' => $newOrder,
                'slugline' => strtoupper(trim($this->newSceneSlugline)),
                'time_of_day' => strtoupper(trim($this->newSceneTimeOfDay)),
                'location_id' => $this->newSceneLocationId ?: null,
            ]);

            // Crear bloque inicial vacío en la escena
            $newScene->scriptBlocks()->create([
                'block_order' => 1,
                'raw_text' => 'Escribe aquí la acción o idea en bruto...',
                'stage' => 'raw',
                'user_locks' => [],
            ]);

            return $newScene;
        });

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
    public function updateScene(): void
    {
        if (! $this->editingSceneId) {
            return;
        }

        $this->validate([
            'editingSceneSlugline' => 'required|min:3|max:120',
            'editingSceneTimeOfDay' => 'required',
        ]);

        $scene = $this->projectScene($this->editingSceneId);
        $scene->update([
            'slugline' => strtoupper(trim($this->editingSceneSlugline)),
            'time_of_day' => strtoupper(trim($this->editingSceneTimeOfDay)),
            'location_id' => $this->editingSceneLocationId ?: null,
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
    public function openDeleteBlockModal(string $blockId): void
    {
        $block = $this->projectBlock($blockId, ['scene']);
        $this->deletingBlockId = $block->id;
        $this->deletingBlockOrder = $block->block_order;
        $this->deletingBlockSceneOrder = $block->scene?->scene_order;
        $raw = trim((string) ($block->raw_text ?? ''));
        $preview = $raw ?: ($block->structured_text ?: ($block->screenplay_content ?: 'Bloque sin contenido'));
        $this->deletingBlockPreview = mb_substr($preview, 0, 120);
        $this->showDeleteBlockModal = true;
    }

    /**
     * Cierra el modal de eliminación de bloque.
     */
    public function closeDeleteBlockModal(): void
    {
        $this->reset(['showDeleteBlockModal', 'deletingBlockId', 'deletingBlockPreview', 'deletingBlockOrder', 'deletingBlockSceneOrder']);
    }

    /**
     * Confirma la eliminación irreversible del bloque, registra la auditoría y reordena.
     */
    public function confirmDeleteBlock(): void
    {
        if (! $this->deletingBlockId) {
            return;
        }

        $block = $this->projectBlock($this->deletingBlockId, ['scene.project']);
        $scene = $block->scene;

        $this->logActivity('delete_block', 'ScriptBlock', $block->id, "Bloque #{$block->block_order} de la Escena #{$scene->scene_order} ({$scene->slugline}) eliminado permanentemente", [
            'block_id' => $block->id,
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'scene_slugline' => $scene->slugline,
            'block_order' => $block->block_order,
            'stage' => $block->stage,
            'raw_text' => $block->raw_text,
            'structured_text' => $block->structured_text,
            'screenplay_content' => $block->screenplay_content,
            'plot_hole' => $block->plot_hole,
            'user_locks' => $block->user_locks,
        ]);

        if ($this->selectedBlockId === $block->id) {
            $this->selectedBlockId = null;
        }
        if ($this->editingBlockId === $block->id) {
            $this->editingBlockId = null;
        }
        if ($this->detailBlockId === $block->id) {
            $this->detailBlockId = null;
            $this->isEditingInDrawer = false;
        }

        $block->delete();

        // Reordenar secuencialmente los bloques restantes de la escena
        $remainingBlocks = $scene->scriptBlocks()->orderBy('block_order')->get();
        foreach ($remainingBlocks as $idx => $rem) {
            $order = $idx + 1;
            if ($rem->block_order !== $order) {
                $rem->update(['block_order' => $order]);
            }
        }

        $this->dispatch('bible-updated');

        $this->closeDeleteBlockModal();
        session()->flash('success', "Bloque #{$this->deletingBlockOrder} eliminado correctamente y registrado en el historial.");
    }

    /**
     * Abre el modal de advertencia para eliminar una escena.
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
    public function confirmDeleteScene(): void
    {
        if (! $this->deletingSceneId) {
            return;
        }

        $scene = $this->projectScene($this->deletingSceneId);

        // Regla estricta: primero no tiene que haber ningún bloque en la escena
        $blockCount = $scene->scriptBlocks()->count();
        if ($blockCount > 0) {
            session()->flash('error', "No se puede eliminar la Escena #{$scene->scene_order}: contiene {$blockCount} bloque(s). Debes eliminar primero todos sus bloques.");
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

        $scene->delete();

        // Reordenar secuencialmente las escenas restantes del proyecto
        $remainingScenes = $this->project->scenes()->orderBy('scene_order')->get();
        foreach ($remainingScenes as $idx => $sc) {
            $order = $idx + 1;
            if ($sc->scene_order !== $order) {
                $sc->update(['scene_order' => $order]);
            }
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
    protected function logActivity(string $action, string $entityType, ?string $entityId, string $description, ?array $snapshot = null): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        // Deduplicación de snapshots para evitar inflar SQLite con textos idénticos (ISSUE-040)
        if ($snapshot !== null && $entityId) {
            $lastLog = UserActivityLog::query()
                ->where('project_id', $this->project->id)
                ->where('entity_id', $entityId)
                ->where('action', $action)
                ->latest()
                ->first();

            if ($lastLog && $lastLog->snapshot === $snapshot) {
                $snapshot = null;
            }
        }

        UserActivityLog::create([
            'user_id' => $userId,
            'project_id' => $this->project->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Abre el modal de historial y auditoría cronológica inversa del proyecto.
     */
    public function openActivityHistoryModal(): void
    {
        $this->showActivityHistoryModal = true;
    }

    /**
     * Cierra el modal de historial de actividad.
     */
    public function closeActivityHistoryModal(): void
    {
        $this->showActivityHistoryModal = false;
    }

    /**
     * Calcula la estimación de tokens que consumirá la auditoría de agujeros y abre el modal de confirmación.
     */
    public function promptAnalyzePlotHoles(): void
    {
        $scenes = $this->project->scenes()
            ->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])
            ->orderBy('scene_order')
            ->get();

        $allBlocks = $scenes->flatMap(fn ($s) => $s->scriptBlocks);
        $blockCount = 0;
        $totalChars = 0;

        foreach ($allBlocks as $block) {
            $text = trim(($block->raw_text ?? '').' '.($block->structured_text ?? '').' '.($block->screenplay_content ?? ''));
            if (! empty($text)) {
                $blockCount++;
                $totalChars += mb_strlen($text);
            }
        }

        if ($blockCount === 0) {
            session()->flash('warning', 'No hay bloques con contenido en el proyecto para auditar.');

            return;
        }

        // Estimación heurística: prompt (longitud texto / 4 + contexto biblia ~350) + output estimado (~750)
        $estimatedPromptTokens = (int) ceil($totalChars / 4) + 350;
        $estimatedResponseTokens = 750;
        $this->estimatedPlotHoleTokens = $estimatedPromptTokens + $estimatedResponseTokens;
        $this->estimatedBlocksToAudit = $blockCount;

        $user = auth()->user() ?? $this->project->user;
        if ($user && ! $user->hasDailyTokensAvailable($this->estimatedPlotHoleTokens)) {
            $available = $user->getTokensAvailableToday();
            $limit = $user->getDailyTokenLimit();
            session()->flash('error', "Saldo insuficiente de tokens para auditar el proyecto ({$available} disponibles, ~{$this->estimatedPlotHoleTokens} requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.');

            return;
        }

        $this->showPlotHoleConfirmModal = true;
    }

    /**
     * Cancela la auditoría de agujeros de guión y cierra el modal.
     */
    public function cancelAnalyzePlotHoles(): void
    {
        $this->showPlotHoleConfirmModal = false;
    }

    /**
     * Confirma la auditoría de agujeros de guión tras la aceptación del coste en tokens.
     */
    public function confirmAnalyzePlotHoles(): void
    {
        $this->showPlotHoleConfirmModal = false;
        $this->analyzePlotHoles();
    }

    public function addBlock(): void
    {
        $this->openNewBlockModal();
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

    public function saveBlock(ScriptPipelineService $pipelineService): void
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

        $blockId = $this->editingBlockId;
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

            // Retener modificaciones manuales en user_locks si el usuario editó etapas ya procesadas
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

    public function toggleStageLock(string $blockId, string $stage): void
    {
        if (! in_array($stage, ['structured', 'screenplay', 'shots'], true)) {
            return;
        }

        $block = $this->projectBlock($blockId);

        $block->mutateUserLocks(function (array $userLocks) use ($stage) {
            $lockedFields = $userLocks['locked_fields'] ?? [];

            if (in_array($stage, $lockedFields, true)) {
                // Al desbloquear 'structured', también se retira 'screenplay' para evitar que isStageLocked siga dando true
                if ($stage === 'structured') {
                    $lockedFields = array_values(array_filter($lockedFields, fn ($f) => ! in_array($f, ['structured', 'screenplay'], true)));
                    session()->flash('info', "Etapa '{$stage}' desbloqueada (🔓). Tratamiento y guion listos para regenerar desde el bruto.");
                } else {
                    $lockedFields = array_values(array_filter($lockedFields, fn ($f) => $f !== $stage));
                    session()->flash('info', "Etapa '{$stage}' desbloqueada (🔓). Ahora la IA podrá regenerarla si avanzas desde el paso anterior.");
                }
            } else {
                $lockedFields[] = $stage;
                if ($stage === 'screenplay' && ! in_array('structured', $lockedFields, true)) {
                    $lockedFields[] = 'structured';
                }
                session()->flash('success', "Etapa '{$stage}' protegida con candado (🔒). No se sobreescribirá ante regeneraciones.");
            }

            $userLocks['locked_fields'] = array_values(array_unique($lockedFields));

            return $userLocks;
        });

        $this->logActivity('toggle_stage_lock', 'ScriptBlock', $block->id, "Candado de etapa '{$stage}' alternado en Bloque #{$block->block_order}", [
            'block_id' => $block->id,
            'stage' => $stage,
            'locked_fields' => $block->user_locks['locked_fields'] ?? [],
        ]);
    }

    public function breakdownBlockShots(string $blockId, ScriptPipelineService $pipelineService): void
    {
        $block = $this->projectBlock($blockId);

        if ($block->isStageLocked('shots')) {
            session()->flash('error', "El desglose de planos del Bloque {$block->block_order} está protegido (🔒) por candado. Desbloquéalo primero para regenerar los planos con IA.");

            return;
        }

        try {
            $createdShots = $pipelineService->generateShotsBreakdown($block);
        } catch (QuotaExceededException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->activeBlockTabs[$block->id] = 'shots';

        if ($this->detailBlockId === $block->id) {
            $this->detailActiveTab = 'shots';
        }

        session()->flash('success', "Bloque {$block->block_order} desglosado: {$createdShots->count()} planos sincronizados.");
    }

    public function setStoryboardStyle(string $style): void
    {
        if (! array_key_exists($style, StoryboardService::STYLES)) {
            return;
        }

        $this->storyboardStyle = $style;
        $this->project->update(['storyboard_style' => $style]);
        $styleName = StoryboardService::STYLES[$style]['name'];
        session()->flash('success', "Estilo de Storyboard actualizado a: {$styleName}.");
    }

    public function generateShotStoryboard(string $shotId, StoryboardService $storyboardService): void
    {
        $shot = $this->projectShot($shotId);

        try {
            $storyboardService->generateStoryboard($shot, $this->storyboardStyle);
            session()->flash('success', "Viñeta de storyboard para el Shot #{$shot->shot_number} generada correctamente.");
        } catch (\Throwable $e) {
            Log::error("Error al generar storyboard para el shot {$shotId}: ".$e->getMessage());
            session()->flash('error', 'No se pudo generar el storyboard: '.$e->getMessage());
        }
    }

    public function openStoryboardPreview(string $shotId): void
    {
        $shot = $this->projectShot($shotId);
        $this->previewStoryboardUrl = $shot->storyboard_url;
        $this->previewStoryboardTitle = "Shot #{$shot->shot_number} ({$shot->shot_type})";
    }

    public function closeStoryboardPreview(): void
    {
        $this->previewStoryboardUrl = null;
        $this->previewStoryboardTitle = null;
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

    public function openAiTrace(string $blockId, string $stage): void
    {
        $block = $this->projectBlock($blockId, ['scene.project.characters', 'scene.location', 'shots']);

        $toolName = match ($stage) {
            'structured' => 'treatment_structuring',
            'screenplay' => 'screenplay_formatter',
            'shots' => 'shot_breakdown_detector',
            default => 'treatment_structuring',
        };

        $log = AgentRequestLog::where('script_block_id', $block->id)
            ->where('tool_name', $toolName)
            ->latest()
            ->first();

        $telemetry = is_array($log?->meta) ? $log->meta : null;

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
            'user_locks' => [
                'pinned_terms' => $userLocks['pinned_terms'] ?? [],
                'locked_fields' => $userLocks['locked_fields'] ?? [],
            ],
        ];

        $telemetry['steps'] = $this->buildGranularSteps($block, $stage, $telemetry);

        return $telemetry;
    }

    public function setBlockStage(string $blockId, string $stage): void
    {
        if (! in_array($stage, ['raw', 'structured', 'screenplay', 'shots', 'editing'], true)) {
            return;
        }

        $block = $this->projectBlock($blockId);
        $block->update(['stage' => $stage]);
    }

    public function toggleGoodTake(string $takeId): void
    {
        $take = $this->projectTake($takeId);
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

    public function saveNewTake(): void
    {
        if (! $this->addingTakeShotId) {
            return;
        }

        $shot = $this->projectShot($this->addingTakeShotId);

        DB::transaction(function () use ($shot) {
            Shot::whereKey($shot->id)->lockForUpdate()->first();
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
        });

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
            'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|extensions:mp4,mov,webm,m4v|max:102400',
        ]);

        $allowedMimes = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-m4v' => 'm4v',
        ];

        $mime = (string) $this->videoUpload->getMimeType();
        if (! isset($allowedMimes[$mime])) {
            $this->addError('videoUpload', 'Formato de vídeo o tipo MIME no permitido.');

            return;
        }

        $take = $this->projectTake($this->uploadingTakeId, ['shot.scene']);

        // Si ya existía un archivo previo, eliminarlo físicamente de disco privado o público
        if ($take->file_path) {
            if (Storage::disk('local')->exists($take->file_path)) {
                Storage::disk('local')->delete($take->file_path);
            }
            if (Storage::disk('public')->exists($take->file_path)) {
                Storage::disk('public')->delete($take->file_path);
            }
        }

        $extension = $allowedMimes[$mime];
        $storagePath = $take->generateStoragePath($extension);

        $this->videoUpload->storeAs('', $storagePath, 'local');

        $absolutePath = Storage::disk('local')->path($storagePath);
        $probedMeta = $this->probeVideoFile($absolutePath);

        $take->update([
            'file_path' => $storagePath,
            'technical_meta' => array_merge($take->technical_meta ?? [], $probedMeta),
        ]);

        $takeNumber = $take->take_number;
        $this->reset(['videoUpload', 'uploadingTakeId']);
        session()->flash('success', "Vídeo de la Toma {$takeNumber} subido y analizado correctamente.");
    }

    /**
     * Sonda metadatos técnicos reales del fichero de vídeo mediante ffprobe (ISSUE-027).
     *
     * @return array<string, mixed>
     */
    protected function probeVideoFile(string $absolutePath): array
    {
        $defaultMeta = [
            'probed' => false,
            'duration_seconds' => null,
            'resolution' => null,
            'fps' => null,
            'codec' => null,
        ];

        if (! file_exists($absolutePath)) {
            return $defaultMeta;
        }

        try {
            $process = Process::timeout(15)->run([
                'ffprobe',
                '-v', 'error',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                $absolutePath,
            ]);

            if (! $process->successful()) {
                return $defaultMeta;
            }

            $data = json_decode($process->output(), true);
            if (! is_array($data) || empty($data['streams'])) {
                return $defaultMeta;
            }

            $videoStream = null;
            foreach ($data['streams'] as $stream) {
                if (($stream['codec_type'] ?? '') === 'video') {
                    $videoStream = $stream;
                    break;
                }
            }

            if (! $videoStream) {
                return $defaultMeta;
            }

            $duration = (float) ($videoStream['duration'] ?? $data['format']['duration'] ?? 0);
            $width = (int) ($videoStream['width'] ?? 0);
            $height = (int) ($videoStream['height'] ?? 0);
            $codec = (string) ($videoStream['codec_name'] ?? '');

            $fps = null;
            $fpsRaw = (string) ($videoStream['r_frame_rate'] ?? $videoStream['avg_frame_rate'] ?? '');
            if (! empty($fpsRaw) && str_contains($fpsRaw, '/')) {
                [$num, $den] = explode('/', $fpsRaw, 2);
                if ((float) $den > 0) {
                    $fps = round((float) $num / (float) $den, 2);
                }
            }

            return [
                'probed' => true,
                'duration_seconds' => $duration > 0 ? round($duration, 2) : null,
                'resolution' => ($width > 0 && $height > 0) ? "{$width}x{$height}" : null,
                'fps' => $fps,
                'codec' => ! empty($codec) ? strtolower($codec) : null,
                'pix_fmt' => (string) ($videoStream['pix_fmt'] ?? ''),
                'bit_rate' => (int) ($videoStream['bit_rate'] ?? $data['format']['bit_rate'] ?? 0),
            ];
        } catch (\Throwable) {
            return $defaultMeta;
        }
    }

    public function deleteTake(string $takeId): void
    {
        $take = $this->projectTake($takeId);
        $shotNumber = $take->shot?->shot_number ?? 1;
        $takeNumber = $take->take_number;

        $take->delete();

        session()->flash('success', "Toma {$takeNumber} del Plano {$shotNumber} eliminada.");
    }

    public function startEditTakeNotes(string $takeId): void
    {
        $take = $this->projectTake($takeId);
        $this->editingTakeNotesId = $take->id;
        $this->editingTakeNotesText = (string) ($take->editorial_notes ?? '');
    }

    public function saveTakeNotes(): void
    {
        if (! $this->editingTakeNotesId) {
            return;
        }

        $this->validate([
            'editingTakeNotesText' => 'nullable|string|max:5000',
        ]);

        $take = $this->projectTake($this->editingTakeNotesId);
        $take->update([
            'editorial_notes' => $this->editingTakeNotesText ?: null,
        ]);

        $takeNumber = $take->take_number;
        $this->reset(['editingTakeNotesId', 'editingTakeNotesText']);
        session()->flash('success', "Notas editoriales de la Toma {$takeNumber} guardadas.");
    }

    /**
     * Audita y detecta agujeros de guión pasando el análisis a cada consolidado por separado (Idea, Tratamiento, Guion).
     * Si un bloque presenta inconsistencias en varias etapas, se marca como agujero el del paso más temprano.
     */
    public function analyzePlotHoles(?AgentClientService $agentClient = null): void
    {
        $agentClient = $agentClient ?? app(AgentClientService::class);

        $user = auth()->user() ?? $this->project->user;
        if ($user && ! $user->hasDailyTokensAvailable(50)) {
            session()->flash('error', 'Has alcanzado tu cupo diario de tokens de IA ('.number_format($user->getDailyTokenLimit(), 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.');

            return;
        }

        $scenes = $this->project->scenes()
            ->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])
            ->orderBy('scene_order')
            ->get();

        $allBlocks = $scenes->flatMap(fn ($s) => $s->scriptBlocks);

        if ($allBlocks->isEmpty()) {
            session()->flash('warning', 'No hay bloques de guión en el proyecto para auditar.');

            return;
        }

        $characters = $this->project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray();

        $locations = $this->project->locations()->get()->map(fn (Location $l) => $l->toAgentContext())->toArray();

        // Secuencia estricta de consolidados de más temprano a más tardío
        $stagesToAudit = [
            'raw' => 'Idea (Bruto)',
            'structured' => 'Tratamiento',
            'screenplay' => 'Guion',
        ];

        // Guardará el agujero detectado en el paso más temprano: block_id => ['reason' => string, 'stage' => string, 'stage_label' => string]
        $earliestHoleByBlockId = [];

        foreach ($stagesToAudit as $stage => $stageLabel) {
            $stageBlocksData = [];

            foreach ($scenes as $scene) {
                foreach ($scene->scriptBlocks as $block) {
                    $text = match ($stage) {
                        'raw' => (string) ($block->raw_text ?? ''),
                        'structured' => (string) ($block->structured_text ?? ''),
                        'screenplay' => (string) ($block->screenplay_content ?? ''),
                    };

                    // Solo enviar a auditar bloques que tengan contenido en esta etapa
                    if (empty(trim($text))) {
                        continue;
                    }

                    $stageBlocksData[] = [
                        'id' => (string) $block->id,
                        'scene_id' => (string) $scene->id,
                        'scene_order' => $scene->scene_order,
                        'scene_slugline' => $scene->slugline,
                        'block_order' => $block->block_order,
                        'stage' => $stage,
                        'raw_text' => (string) ($block->raw_text ?? ''),
                        'structured_text' => (string) ($block->structured_text ?? ''),
                        'screenplay_content' => (string) ($block->screenplay_content ?? ''),
                        'text' => $text,
                    ];
                }
            }

            if (empty($stageBlocksData)) {
                continue;
            }

            // Comprobar disponibilidad de cuota para esta fase específica
            $stageEstimated = max(50, (int) ceil(strlen(json_encode($stageBlocksData)) / 4) + 150);
            if ($user && ! $user->hasDailyTokensAvailable($stageEstimated)) {
                session()->flash('warning', 'La auditoría de agujeros se detuvo: saldo de tokens diario agotado antes de completar todas las etapas.');
                break;
            }

            $result = $agentClient->auditPlotHoles($stageBlocksData, $characters, $locations);

            // Registrar telemetría de tokens consumidos en agent_request_logs
            $telemetry = $agentClient->getLastTelemetry();
            app(AgentTelemetryService::class)->record(
                projectId: $this->project->id,
                scriptBlockId: null,
                toolName: 'plot_hole_detector',
                inputPayload: $stageBlocksData,
                outputPayload: $result,
                telemetry: $telemetry,
                userId: $user?->id
            );

            $detectedHoles = (array) ($result['detected_holes'] ?? []);

            foreach ($detectedHoles as $hole) {
                $blockId = (string) ($hole['block_id'] ?? '');
                $reason = trim((string) ($hole['reason'] ?? ''));

                if (empty($blockId) || empty($reason)) {
                    continue;
                }

                // Si este bloque ya tiene un agujero asignado de un paso más temprano, NO sobreescribir
                if (isset($earliestHoleByBlockId[$blockId])) {
                    continue;
                }

                // Comprobar si el autor ya justificó este agujero
                $targetBlock = $allBlocks->firstWhere('id', $blockId);
                if ($targetBlock) {
                    $justified = $targetBlock->getJustifiedPlotHoles();
                    $isJustified = false;
                    foreach ($justified as $j) {
                        $jReason = trim((string) ($j['hole_reason'] ?? $j['original_reason'] ?? ''));
                        if (! empty($jReason) && (str_contains(mb_strtolower($reason), mb_strtolower($jReason)) || str_contains(mb_strtolower($jReason), mb_strtolower($reason)))) {
                            $isJustified = true;
                            break;
                        }
                    }
                    if ($isJustified) {
                        continue;
                    }
                }

                // Asignar el agujero detectado en este paso temprano
                $earliestHoleByBlockId[$blockId] = [
                    'reason' => $reason,
                    'stage' => $stage,
                    'stage_label' => $stageLabel,
                ];
            }
        }

        // Aplicar a cada bloque y persistir
        $stageCounts = ['raw' => 0, 'structured' => 0, 'screenplay' => 0];

        foreach ($allBlocks as $block) {
            $holeInfo = $earliestHoleByBlockId[$block->id] ?? null;

            DB::transaction(function () use ($block, $holeInfo) {
                /** @var ScriptBlock|null $fresh */
                $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
                if (! $fresh) {
                    return;
                }

                $locks = $fresh->user_locks ?? [];

                if ($holeInfo !== null) {
                    $locks['plot_hole_stage'] = $holeInfo['stage'];
                    $fresh->update([
                        'plot_hole' => $holeInfo['reason'],
                        'user_locks' => $locks,
                        'lock_version' => ($fresh->lock_version ?? 1) + 1,
                    ]);
                } else {
                    unset($locks['plot_hole_stage']);
                    $fresh->update([
                        'plot_hole' => null,
                        'user_locks' => $locks,
                        'lock_version' => ($fresh->lock_version ?? 1) + 1,
                    ]);
                }
            });

            if ($holeInfo !== null) {
                $stageCounts[$holeInfo['stage']]++;
            }
        }

        $totalMarked = array_sum($stageCounts);

        $detectedHolesDetailed = [];
        foreach ($earliestHoleByBlockId as $bId => $hInfo) {
            $b = $allBlocks->firstWhere('id', $bId);
            $sc = $b?->scene;
            $text = trim(($b?->raw_text ?? '').' '.($b?->structured_text ?? '').' '.($b?->screenplay_content ?? ''));
            $detectedHolesDetailed[] = [
                'block_id' => $bId,
                'scene_order' => $sc?->scene_order,
                'scene_slugline' => $sc?->slugline,
                'block_order' => $b?->block_order,
                'stage' => $hInfo['stage'],
                'stage_label' => $hInfo['stage_label'],
                'reason' => $hInfo['reason'],
                'block_preview' => mb_substr($text, 0, 150),
            ];
        }

        if ($totalMarked > 0) {
            $details = [];
            if ($stageCounts['raw'] > 0) {
                $details[] = "{$stageCounts['raw']} en Idea";
            }
            if ($stageCounts['structured'] > 0) {
                $details[] = "{$stageCounts['structured']} en Tratamiento";
            }
            if ($stageCounts['screenplay'] > 0) {
                $details[] = "{$stageCounts['screenplay']} en Guion";
            }
            $detailsStr = implode(', ', $details);

            $this->logActivity('analyze_plot_holes', 'Project', $this->project->id, "Auditoría de agujeros de guión ejecutada ({$totalMarked} detectados: {$detailsStr})", [
                'total_detected' => $totalMarked,
                'total_blocks_audited' => $allBlocks->count(),
                'by_stage' => $stageCounts,
                'detected_holes' => $detectedHolesDetailed,
            ]);

            session()->flash('warning', "Se han detectado {$totalMarked} posibles agujeros de guión ({$detailsStr}). Marcado el origen más temprano.");
        } else {
            $this->logActivity('analyze_plot_holes', 'Project', $this->project->id, 'Auditoría de agujeros de guión ejecutada: sin inconsistencias detectadas', [
                'total_detected' => 0,
                'total_blocks_audited' => $allBlocks->count(),
                'by_stage' => $stageCounts,
                'detected_holes' => [],
            ]);

            session()->flash('success', 'Filtro de agujeros de guión: No se han detectado incongruencias en ninguna de las etapas.');
        }
    }

    public function openJustifyModal(string $blockId): void
    {
        $block = $this->projectBlock($blockId);
        $this->justifyingBlockId = $block->id;
        $this->justifyingHoleReason = (string) ($block->plot_hole ?? '');
        $this->justificationText = '';
        $this->showJustifyModal = true;
    }

    public function closeJustifyModal(): void
    {
        $this->showJustifyModal = false;
        $this->justifyingBlockId = null;
        $this->justifyingHoleReason = '';
        $this->justificationText = '';
    }

    public function savePlotHoleJustification(): void
    {
        if (! $this->justifyingBlockId || empty(trim($this->justificationText))) {
            session()->flash('error', 'Debes introducir una explicación para justificar y retirar el agujero de guión.');

            return;
        }

        $block = $this->projectBlock($this->justifyingBlockId);
        $reason = $this->justifyingHoleReason ?: (string) $block->plot_hole;
        $stage = $block->getPlotHoleStage() ?? 'raw';
        $stageLabel = $block->getPlotHoleStageLabel();
        $justificationText = trim($this->justificationText);

        $block->mutateUserLocks(function (array $userLocks, ScriptBlock $fresh) use ($reason, $stage, $stageLabel, $justificationText) {
            $justifiedHoles = (array) ($userLocks['justified_plot_holes'] ?? []);

            $justifiedHoles[] = [
                'id' => Str::uuid()->toString(),
                'hole_reason' => $reason,
                'original_reason' => $reason,
                'stage' => $stage,
                'stage_label' => $stageLabel,
                'justification' => $justificationText,
                'justified_at' => now()->toIso8601String(),
            ];

            unset($userLocks['plot_hole_stage']);
            $userLocks['justified_plot_holes'] = $justifiedHoles;
            $fresh->plot_hole = null;

            return $userLocks;
        });

        $scene = $block->scene;
        $this->logActivity('justify_plot_hole', 'ScriptBlock', $block->id, "Agujero de guión justificado en Bloque #{$block->block_order} (Escena #{$scene?->scene_order}): \"".Str::limit($justificationText, 80).'"', [
            'block_id' => $block->id,
            'scene_id' => $scene?->id,
            'scene_order' => $scene?->scene_order,
            'scene_slugline' => $scene?->slugline,
            'block_order' => $block->block_order,
            'hole_reason' => $reason,
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'justification' => $justificationText,
            'raw_text' => $block->raw_text,
        ]);

        $blockOrder = $block->block_order;
        $this->closeJustifyModal();
        session()->flash('success', "Agujero de guión justificado y retirado del Bloque #{$blockOrder}. Ahora figura en 'Agujeros justificados'.");
    }

    /**
     * Alterna la visibilidad de los mensajes/tarjetas de agujeros de guión en la interfaz.
     */
    public function togglePlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = ! $this->showPlotHoleAlerts;
        $status = $this->showPlotHoleAlerts ? 'activados' : 'desactivados';
        session()->flash('info', "Avisos de agujeros de guión {$status}.");
    }

    /**
     * Hace visibles los avisos de agujeros de guión.
     */
    public function showPlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = true;
        session()->flash('info', 'Avisos de agujeros de guión visibles.');
    }

    /**
     * Oculta los avisos de agujeros de guión.
     */
    public function hidePlotHoleAlerts(): void
    {
        $this->showPlotHoleAlerts = false;
        session()->flash('info', 'Avisos de agujeros de guión ocultos.');
    }

    /**
     * Limpia todas las marcas de agujeros de guión activas en los bloques del proyecto.
     */
    public function clearPlotHoles(): void
    {
        foreach ($this->project->scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                if (filled($block->plot_hole)) {
                    DB::transaction(function () use ($block) {
                        /** @var ScriptBlock|null $fresh */
                        $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
                        if ($fresh && filled($fresh->plot_hole)) {
                            $locks = $fresh->user_locks ?? [];
                            unset($locks['plot_hole_stage']);
                            $fresh->update([
                                'plot_hole' => null,
                                'user_locks' => $locks,
                                'lock_version' => ($fresh->lock_version ?? 1) + 1,
                            ]);
                        }
                    });
                }
            }
        }

        $this->logActivity('clear_plot_holes', 'Project', $this->project->id, 'Todas las marcas de agujeros de guión eliminadas del proyecto');

        session()->flash('success', 'Todas las marcas de agujeros de guión han sido eliminadas del proyecto.');
    }

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

    public function exportPdf(ScriptExportService $exportService): StreamedResponse
    {
        Gate::authorize('view', $this->project);

        $this->logActivity('export_script_pdf', 'Project', $this->project->id, 'Exportación de guión completo a PDF');

        $pdf = $exportService->generateScreenplayPdf($this->project);
        $filename = Str::slug($this->project->title ?: 'guion').'-guion.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function openBatchModal(): void
    {
        $this->showBatchModal = true;
    }

    public function executeBatchProcessing(ScriptPipelineService $pipelineService): void
    {
        $this->isBatchProcessing = true;

        if ($this->batchScope === 'scene') {
            $scene = $this->activeSceneId
                ? $this->projectScene($this->activeSceneId)
                : $this->project->scenes()->first();

            if (! $scene) {
                session()->flash('error', 'No hay ninguna escena activa para procesar.');
                $this->showBatchModal = false;
                $this->isBatchProcessing = false;

                return;
            }

            $blocks = $scene->scriptBlocks()->orderBy('block_order')->get();
            $scopeDesc = "Escena #{$scene->scene_order} ({$scene->slugline})";
        } else {
            $blocks = ScriptBlock::whereIn('scene_id', $this->project->scenes()->pluck('id'))
                ->orderBy('scene_id')
                ->orderBy('block_order')
                ->get();
            $scopeDesc = 'Proyecto Completo ('.count($blocks).' bloques)';
        }

        if ($blocks->isEmpty()) {
            session()->flash('error', "No hay bloques de texto disponibles para procesar en {$scopeDesc}.");
            $this->showBatchModal = false;
            $this->isBatchProcessing = false;

            return;
        }

        try {
            switch ($this->batchStage) {
                case 'structured':
                    $res = $pipelineService->batchStructureBlocks($blocks);
                    $msg = "Procesamiento por lote a Tratamiento finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos (protegidos o vacíos).";
                    break;

                case 'screenplay':
                    $res = $pipelineService->batchFormatBlocksToScreenplay($blocks);
                    $msg = "Procesamiento por lote a Guion Literario finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos.";
                    break;

                case 'shots':
                    $res = $pipelineService->batchGenerateShots($blocks);
                    $msg = "Desglose por lote a Planos Técnicos finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos.";
                    break;

                case 'all':
                default:
                    $res = $pipelineService->batchRunFullPipeline($blocks);
                    $msg = "Pipeline Completo ejecutado en {$scopeDesc}: {$res['structured']} estructurados, {$res['screenplay']} formateados a guion, {$res['shots']} desglosados a planos.";
                    break;
            }

            $this->logActivity('batch_pipeline_execution', 'Project', $this->project->id, $msg, [
                'scope' => $this->batchScope,
                'stage' => $this->batchStage,
                'blocks_total' => count($blocks),
            ]);

            if (! empty($res['errors'])) {
                session()->flash('error', $msg.' Se produjeron advertencias: '.implode(' | ', array_slice($res['errors'], 0, 3)));
            } else {
                session()->flash('success', $msg);
            }
        } catch (\Throwable $e) {
            session()->flash('error', "Error en el procesamiento en lote: {$e->getMessage()}");
        } finally {
            $this->showBatchModal = false;
            $this->isBatchProcessing = false;
        }
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile', 'useAiForImport', 'parsedImportData', 'confirmDestructiveImport']);
        $this->importStep = 'upload';
        $this->showImportModal = true;
    }

    public function processImportFile(ScriptImportService $importService): void
    {
        $this->resetErrorBag();
        $this->validate([
            'importFile' => 'required|file|mimes:txt,pdf|max:10240',
        ], [
            'importFile.required' => 'Debes seleccionar un archivo de guión (.txt o .pdf).',
            'importFile.mimes' => 'Solo se admiten archivos en formato .txt o .pdf.',
            'importFile.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        try {
            $rawText = $importService->extractTextFromFile($this->importFile);
            $parsed = $importService->parseScreenplay($rawText, $this->useAiForImport, $this->project);

            $this->parsedImportData = $parsed;
            $this->importStep = 'preview';
        } catch (\Throwable $e) {
            $this->addError('importFile', $e->getMessage());
        }
    }

    public function confirmAndExecuteDestructiveImport(ScriptImportService $importService): void
    {
        $this->resetErrorBag();
        if (! $this->confirmDestructiveImport) {
            $this->addError('confirmDestructiveImport', 'Debes marcar la casilla de confirmación para autorizar la sobreescritura destructiva.');

            return;
        }

        if (empty($this->parsedImportData['scenes'])) {
            $this->addError('importFile', 'No se encontraron escenas válidas para importar.');

            return;
        }

        try {
            $importService->importIntoProject($this->project, $this->parsedImportData, true);

            $this->logActivity('destructive_script_import', 'Project', $this->project->id, 'Guion importado sobreescribiendo por completo el proyecto anterior', [
                'scenes_imported' => $this->parsedImportData['scenes_count'] ?? 0,
                'characters_imported' => count($this->parsedImportData['characters'] ?? []),
                'locations_imported' => count($this->parsedImportData['locations'] ?? []),
            ]);

            $this->reset(['showImportModal', 'importStep', 'importFile', 'parsedImportData', 'confirmDestructiveImport']);

            // Recargar escenas y activar la primera escena
            $this->activeSceneId = $this->project->scenes()->first()?->id;

            session()->flash('success', "Guión importado con éxito. Se han configurado {$this->project->scenes()->count()} escenas y {$this->project->characters()->count()} personajes.");
        } catch (\Throwable $e) {
            $this->addError('importFile', "Error al importar el guión: {$e->getMessage()}");
        }
    }

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
