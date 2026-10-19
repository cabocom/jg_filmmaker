<?php

namespace App\Livewire\Projects;

use App\Services\ScriptImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Mis Proyectos Cinematográficos'])]
class ProjectList extends Component
{
    use WithFileUploads;

    public bool $showCreateModal = false;

    #[Rule('required|min:3|max:120')]
    public string $title = '';

    #[Rule('nullable|max:1000')]
    public string $synopsis = '';

    #[Rule('required|in:draft,in_progress,completed,archived')]
    public string $status = 'draft';

    // Modal para Editar Proyecto Existente
    public bool $showEditModal = false;

    public ?string $editingProjectId = null;

    #[Rule('required|min:3|max:120')]
    public string $editTitle = '';

    #[Rule('nullable|max:1000')]
    public string $editSynopsis = '';

    #[Rule('required|in:draft,in_progress,completed,archived')]
    public string $editStatus = 'draft';

    // Modal para Importar Guión y Crear Proyecto
    public bool $showImportModal = false;

    public string $importStep = 'upload'; // 'upload', 'preview'

    public $importFile = null;

    public string $importProjectTitle = '';

    public string $importProjectSynopsis = '';

    public bool $useAiForImport = false;

    /** @var array<string, mixed> */
    public array $parsedImportData = [];

    public function createProject(): void
    {
        $this->validateOnly('title');
        $this->validateOnly('synopsis');
        $this->validateOnly('status');

        $user = Auth::user();

        // Validar límite de cuota
        $currentCount = $user->projects()->count();
        $allowedSlots = $user->paid_project_slots ?? 5;

        if ($currentCount >= $allowedSlots) {
            $this->addError('quota', "Has alcanzado el límite de proyectos permitidos ({$allowedSlots} slots en tu plan).");

            return;
        }

        $project = $user->projects()->create([
            'title' => $this->title,
            'synopsis' => $this->synopsis,
            'status' => $this->status,
        ]);

        $this->reset(['title', 'synopsis', 'status', 'showCreateModal']);

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
        $this->showEditModal = true;
    }

    public function updateProject(): void
    {
        $this->validateOnly('editTitle');
        $this->validateOnly('editSynopsis');
        $this->validateOnly('editStatus');

        if (! $this->editingProjectId) {
            return;
        }

        $project = Auth::user()->projects()->findOrFail($this->editingProjectId);

        $project->update([
            'title' => $this->editTitle,
            'synopsis' => $this->editSynopsis,
            'status' => $this->editStatus,
        ]);

        $this->reset(['showEditModal', 'editingProjectId', 'editTitle', 'editSynopsis', 'editStatus']);

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

        // Validar límite de cuota
        $currentCount = $user->projects()->count();
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
        ]);

        $importService->importIntoProject($project, $this->parsedImportData, false);

        $this->reset(['showImportModal', 'importStep', 'importFile', 'importProjectTitle', 'importProjectSynopsis', 'parsedImportData']);

        session()->flash('success', "Proyecto \"{$project->title}\" creado e importado con éxito ({$project->scenes()->count()} escenas y {$project->characters()->count()} personajes).");

        $this->redirectRoute('projects.show', ['project' => $project->id]);
    }

    public function render()
    {
        $user = Auth::user();

        $projects = $user ? $user->projects()
            ->withCount(['scenes', 'characters', 'locations'])
            ->latest()
            ->get() : collect();

        return view('livewire.projects.project-list', [
            'projects' => $projects,
            'user' => $user,
        ]);
    }
}
