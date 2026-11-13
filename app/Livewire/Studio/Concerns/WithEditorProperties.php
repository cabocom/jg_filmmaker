<?php

namespace App\Livewire\Studio\Concerns;

use App\Models\Project;
use Livewire\Attributes\Locked;

trait WithEditorProperties
{
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

    // Modal de composición avanzada y generación de Storyboard con IA
    public bool $showStoryboardModal = false;

    public ?string $storyboardShotId = null;

    public string $storyboardModalStyle = 'grayscale_sketch';

    public string $storyboardModalPrompt = '';

    public ?string $storyboardCharacterId = null;

    public ?string $storyboardLocationId = null;

    /** @var array<int, mixed> */
    public array $storyboardPropUploads = [];

    public bool $storyboardPromptCustomized = false;

    // Edición individual de un plano técnico
    public ?string $editingShotId = null;

    public string $editingShotType = 'PM';

    public string $editingShotDescription = '';

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

    // Modal de estimación previa y confirmación de coste en tokens para pipeline completo de escena
    public bool $showScenePipelineConfirmModal = false;

    public ?string $scenePipelineSceneId = null;

    public int $estimatedSceneTokens = 0;

    public int $estimatedSceneBlocks = 0;

    public ?string $scenePipelineSceneSlugline = null;

    public ?int $scenePipelineSceneOrder = null;

    // Estimación reactiva de tokens para procesamiento por lote
    public int $estimatedBatchTokens = 0;

    public int $estimatedBatchBlocks = 0;

    // Modal de confirmación previa de tokens para acciones de bloque individual en Drawer
    public bool $showBlockActionConfirmModal = false;

    public ?string $confirmingBlockId = null;

    public ?string $confirmingBlockAction = null;

    public int $estimatedBlockActionTokens = 0;

    public ?int $confirmingBlockOrder = null;

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
}
