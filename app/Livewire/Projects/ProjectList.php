<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Enums\ProjectCategory;
use App\Services\ScriptImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Mis Proyectos Cinematográficos'])]
class ProjectList extends Component
{
    use WithFileUploads;

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = 'all';

    public bool $showCreateModal = false;

    #[Rule('required|min:3|max:120')]
    public string $title = '';

    #[Rule('nullable|max:1000')]
    public string $synopsis = '';

    #[Rule('required|in:draft,in_progress,completed,archived')]
    public string $status = 'draft';

    #[Rule('required|in:personal,ai_video')]
    public string $category = 'personal';

    // Modal para Editar Proyecto Existente
    public bool $showEditModal = false;

    public ?string $editingProjectId = null;

    #[Rule('required|min:3|max:120')]
    public string $editTitle = '';

    #[Rule('nullable|max:1000')]
    public string $editSynopsis = '';

    #[Rule('required|in:draft,in_progress,completed,archived')]
    public string $editStatus = 'draft';

    #[Rule('required|in:personal,ai_video')]
    public string $editCategory = 'personal';

    // Modal para Importar Guión y Crear Proyecto
    public bool $showImportModal = false;

    public string $importStep = 'upload'; // 'upload', 'preview'

    public $importFile = null;

    public string $importProjectTitle = '';

    public string $importProjectSynopsis = '';

    public bool $useAiForImport = false;

    /** @var array<string, mixed> */
    public array $parsedImportData = [];

    public function setCategoryFilter(string $category): void
    {
        $this->categoryFilter = in_array($category, ['all', ProjectCategory::PERSONAL->value, ProjectCategory::AI_VIDEO->value], true)
            ? $category
            : 'all';
    }

    public function createProject(): void
    {
        $this->validateOnly('title');
        $this->validateOnly('synopsis');
        $this->validateOnly('status');
        $this->validateOnly('category');

        $user = Auth::user();

        if ($this->category === ProjectCategory::PERSONAL->value) {
            // Validar límite de cuota solo para proyectos personales
            $currentCount = $user->projects()
                ->where('category', ProjectCategory::PERSONAL->value)
                ->count();
            $allowedSlots = $user->paid_project_slots ?? 5;

            if ($currentCount >= $allowedSlots) {
                $this->addError('quota', "Has alcanzado el límite de proyectos permitidos ({$allowedSlots} slots en tu plan).");

                return;
            }
        }

        $project = $user->projects()->create([
            'title' => $this->title,
            'synopsis' => $this->synopsis,
            'status' => $this->status,
            'category' => $this->category,
        ]);

        $this->reset(['title', 'synopsis', 'status', 'category', 'showCreateModal']);

        session()->flash('success', "Proyecto \"{$project->title}\" creado con éxito.");

        $this->redirectRoute('projects.show', ['project' => $project->id]);
    }

    public function openEditModal(string $projectId): void
    {
        $project = Auth::user()->projects()->findOrFail($projectId);

        $this->editingProjectId = $project->id;
        $this->editTitle = $project->title;
        $this->editSynopsis = (string) ($project->synopsis ?? '');
        $this->editStatus = $project->status;
        $this->editCategory = $project->categoryEnum()->value;
        $this->showEditModal = true;
    }

    public function updateProject(): void
    {
        $this->validateOnly('editTitle');
        $this->validateOnly('editSynopsis');
        $this->validateOnly('editStatus');
        $this->validateOnly('editCategory');

        if (! $this->editingProjectId) {
            return;
        }

        $project = Auth::user()->projects()->findOrFail($this->editingProjectId);

        $project->update([
            'title' => $this->editTitle,
            'synopsis' => $this->editSynopsis,
            'status' => $this->editStatus,
            'category' => $this->editCategory,
        ]);

        $this->reset(['showEditModal', 'editingProjectId', 'editTitle', 'editSynopsis', 'editStatus', 'editCategory']);

        session()->flash('success', "Proyecto \"{$project->title}\" actualizado con éxito.");
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile', 'importProjectTitle', 'importProjectSynopsis', 'useAiForImport', 'parsedImportData']);
        $this->importStep = 'upload';
        $this->showImportModal = true;
    }

    public function processImportFile(ScriptImportService $importService): void
    {
        $this->resetErrorBag();
        $this->validate([
            'importFile' => 'required|file|mimes:txt,pdf|max:10240', // 10MB
        ], [
            'importFile.required' => 'Debes seleccionar un archivo de guión (.txt o .pdf).',
            'importFile.mimes' => 'Solo se admiten archivos en formato .txt o .pdf.',
            'importFile.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        try {
            $rawText = $importService->extractTextFromFile($this->importFile);
            $parsed = $importService->parseScreenplay($rawText, $this->useAiForImport);

            $this->parsedImportData = $parsed;

            // Deducir título a partir del nombre del archivo si no está establecido
            if (empty($this->importProjectTitle)) {
                $filename = pathinfo((string) $this->importFile->getClientOriginalName(), PATHINFO_FILENAME);
                $this->importProjectTitle = ucwords(str_replace(['_', '-'], ' ', $filename));
            }

            $this->importStep = 'preview';
        } catch (\Throwable $e) {
            $this->addError('importFile', $e->getMessage());
        }
    }

    public function confirmAndCreateProjectFromImport(ScriptImportService $importService): void
    {
        $this->validate([
            'importProjectTitle' => 'required|min:3|max:120',
            'importProjectSynopsis' => 'nullable|max:1000',
        ], [
            'importProjectTitle.required' => 'El título de la obra es obligatorio.',
            'importProjectTitle.min' => 'El título debe tener al menos 3 caracteres.',
        ]);

        $user = Auth::user();

        // Validar límite de cuota solo para proyectos personales
        $currentCount = $user->projects()
            ->where('category', ProjectCategory::PERSONAL->value)
            ->count();
        $allowedSlots = $user->paid_project_slots ?? 5;

        if ($currentCount >= $allowedSlots) {
            $this->addError('quota', "Has alcanzado el límite de proyectos permitidos ({$allowedSlots} slots en tu plan).");

            return;
        }

        if (empty($this->parsedImportData['scenes'])) {
            $this->addError('importFile', 'No se encontraron escenas válidas para importar.');

            return;
        }

        $project = $user->projects()->create([
            'title' => $this->importProjectTitle,
            'synopsis' => $this->importProjectSynopsis ?: 'Guion importado automáticamente.',
            'status' => 'draft',
            'category' => ProjectCategory::PERSONAL->value,
        ]);

        $importService->importIntoProject($project, $this->parsedImportData, false);

        $this->reset(['showImportModal', 'importStep', 'importFile', 'importProjectTitle', 'importProjectSynopsis', 'parsedImportData']);

        session()->flash('success', "Proyecto \"{$project->title}\" creado e importado con éxito ({$project->scenes()->count()} escenas y {$project->characters()->count()} personajes).");

        $this->redirectRoute('projects.show', ['project' => $project->id]);
    }

    public function render()
    {
        $user = Auth::user();

        $personalCount = $user
            ? $user->projects()->where('category', ProjectCategory::PERSONAL->value)->count()
            : 0;
        $aiVideoCount = $user
            ? $user->projects()->where('category', ProjectCategory::AI_VIDEO->value)->count()
            : 0;
        $allCount = $user ? $user->projects()->count() : 0;

        $projects = $user ? $user->projects()
            ->when(
                in_array($this->categoryFilter, [ProjectCategory::PERSONAL->value, ProjectCategory::AI_VIDEO->value], true),
                fn ($query) => $query->where('category', $this->categoryFilter)
            )
            ->withCount(['scenes', 'characters', 'locations'])
            ->latest()
            ->get() : collect();

        return view('livewire.projects.project-list', [
            'projects' => $projects,
            'user' => $user,
            'categoryCounts' => [
                'all' => $allCount,
                'personal' => $personalCount,
                'ai_video' => $aiVideoCount,
            ],
            'personalUsedSlots' => $personalCount,
        ]);
    }
}
