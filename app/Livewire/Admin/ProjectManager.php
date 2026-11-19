<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\TransferProjectOwnershipAction;
use App\Models\Project;
use App\Models\User;
use App\Queries\Admin\GetProjectAiTelemetrySummaryQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Gestión y Migración de Proyectos - Administración jG Filmmaker')]
class ProjectManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterUserId = '';

    // Estado del modal de transferencia de obra
    public bool $showTransferModal = false;

    public ?string $selectedProjectId = null;

    public ?string $selectedProjectTitle = null;

    public ?string $currentOwnerId = null;

    public ?string $currentOwnerName = null;

    public ?string $targetUserId = null;

    public function openTransferModal(string $projectId): void
    {
        $this->authorize('admin');

        $project = Project::with('user')->findOrFail($projectId);

        $this->selectedProjectId = $project->id;
        $this->selectedProjectTitle = $project->title;
        $this->currentOwnerId = $project->user_id;
        $this->currentOwnerName = $project->user ? $project->user->name : 'Sin asignar';
        $this->targetUserId = null;
        $this->resetErrorBag();
        $this->showTransferModal = true;
    }

    public function closeTransferModal(): void
    {
        $this->showTransferModal = false;
        $this->selectedProjectId = null;
        $this->selectedProjectTitle = null;
        $this->currentOwnerId = null;
        $this->currentOwnerName = null;
        $this->targetUserId = null;
        $this->resetErrorBag();
    }

    public function transferProject(?TransferProjectOwnershipAction $action = null): void
    {
        $this->authorize('admin');

        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'targetUserId' => 'required|exists:users,id',
        ]);

        if ($this->targetUserId === $this->currentOwnerId) {
            $this->addError('targetUserId', 'El usuario seleccionado ya es el titular de este proyecto.');

            return;
        }

        try {
            $action ??= app(TransferProjectOwnershipAction::class);
            $project = $action->execute($this->selectedProjectId, $this->targetUserId);
            $targetUser = User::findOrFail($this->targetUserId);

            $this->closeTransferModal();
            session()->flash('status', "El proyecto '{$project->title}' se ha transferido exitosamente a {$targetUser->name} ({$targetUser->email}).");
        } catch (\InvalidArgumentException $e) {
            $this->addError('targetUserId', $e->getMessage());
        }
    }

    public function render()
    {
        // Consulta base con búsqueda y filtros (Privacidad estricta: Cero SELECT de textos de guión)
        $projectsQuery = Project::query()
            ->select(['id', 'user_id', 'title', 'status', 'created_at'])
            ->with(['user' => fn ($q) => $q->select(['id', 'name', 'email', 'plan_tier'])])
            ->withCount('scenes')
            ->orderBy('created_at', 'desc');

        if (! empty($this->search)) {
            $searchTerm = '%'.trim($this->search).'%';
            $projectsQuery->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhereHas('user', function ($uq) use ($searchTerm) {
                        $uq->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        if (! empty($this->filterUserId)) {
            $projectsQuery->where('user_id', $this->filterUserId);
        }

        $projects = $projectsQuery->paginate(20);
        $projectIds = $projects->pluck('id')->toArray();

        // 1. Agregación cuantitativa de bloques por etapa (exclusivamente para los proyectos de la página actual)
        $telemetryQuery = app(GetProjectAiTelemetrySummaryQuery::class);
        $blockCountsByProject = $telemetryQuery->getBlockCountsByStage($projectIds);
        $aiMetricsByProject = $telemetryQuery->getForProjects($projectIds);

        // Cargar usuarios para el selector de transferencia únicamente si el modal está abierto (ISSUE-022)
        $allUsers = $this->showTransferModal
            ? User::withCount('projects')->orderBy('name')->get()
            : collect();

        return view('livewire.admin.project-manager', [
            'projects' => $projects,
            'blockCountsByProject' => $blockCountsByProject,
            'aiMetricsByProject' => $aiMetricsByProject,
            'allUsers' => $allUsers,
        ]);
    }
}
