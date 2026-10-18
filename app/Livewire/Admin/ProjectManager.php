<?php

namespace App\Livewire\Admin;

use App\Models\AdminAuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Gestión y Migración de Proyectos - Administración jG Filmmaker')]
class ProjectManager extends Component
{
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

    public function transferProject(): void
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

        $project = Project::findOrFail($this->selectedProjectId);
        $previousOwnerId = $project->user_id;
        $targetUser = User::findOrFail($this->targetUserId);

        DB::transaction(function () use ($project, $targetUser, $previousOwnerId) {
            $project->user_id = $targetUser->id;
            $project->save();

            AdminAuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_project',
                'target_type' => Project::class,
                'target_id' => $project->id,
                'details' => [
                    'project_title' => $project->title,
                    'previous_owner_id' => $previousOwnerId,
                    'new_owner_id' => $targetUser->id,
                    'new_owner_email' => $targetUser->email,
                ],
                'ip_address' => request()->ip(),
            ]);
        });

        $projectTitle = $project->title;
        $targetName = $targetUser->name;
        $targetEmail = $targetUser->email;

        $this->closeTransferModal();

        session()->flash('status', "El proyecto '{$projectTitle}' se ha transferido exitosamente a {$targetName} ({$targetEmail}).");
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

        $projects = $projectsQuery->get();
        $projectIds = $projects->pluck('id')->toArray();

        // 1. Agregación cuantitativa de bloques por etapa
        $blockCountsByProject = [];
        if (! empty($projectIds)) {
            $blockStageRows = DB::table('script_blocks')
                ->join('scenes', 'script_blocks.scene_id', '=', 'scenes.id')
                ->whereIn('scenes.project_id', $projectIds)
                ->select('scenes.project_id', 'script_blocks.stage', DB::raw('COUNT(*) as total'))
                ->groupBy('scenes.project_id', 'script_blocks.stage')
                ->get();

            foreach ($blockStageRows as $row) {
                $blockCountsByProject[$row->project_id][$row->stage] = (int) $row->total;
            }
        }

        // 2. Telemetría de IA de cada proyecto
        $aiMetricsByProject = [];
        if (! empty($projectIds)) {
            $aiRows = DB::table('agent_request_logs')
                ->whereIn('project_id', $projectIds)
                ->select(
                    'project_id',
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('SUM(estimated_cost_usd) as total_cost_usd')
                )
                ->groupBy('project_id')
                ->get();

            foreach ($aiRows as $row) {
                $aiMetricsByProject[$row->project_id] = [
                    'total_requests' => (int) $row->total_requests,
                    'total_tokens' => (int) $row->total_tokens,
                    'total_cost_usd' => (float) $row->total_cost_usd,
                ];
            }
        }

        $allUsers = User::withCount('projects')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.project-manager', [
            'projects' => $projects,
            'blockCountsByProject' => $blockCountsByProject,
            'aiMetricsByProject' => $aiMetricsByProject,
            'allUsers' => $allUsers,
        ]);
    }
}
