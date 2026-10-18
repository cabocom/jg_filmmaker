<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Auditoría de Usuario - Administración jG Filmmaker')]
class UserDetail extends Component
{
    public User $user;

    public bool $showTransferModal = false;

    public ?string $selectedProjectId = null;

    public ?string $selectedProjectTitle = null;

    public ?string $targetUserId = null;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function openTransferModal(string $projectId): void
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $this->selectedProjectId = $project->id;
        $this->selectedProjectTitle = $project->title;
        $this->targetUserId = null;
        $this->resetErrorBag();
        $this->showTransferModal = true;
    }

    public function closeTransferModal(): void
    {
        $this->showTransferModal = false;
        $this->selectedProjectId = null;
        $this->selectedProjectTitle = null;
        $this->targetUserId = null;
        $this->resetErrorBag();
    }

    public function transferProject(): void
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'targetUserId' => 'required|exists:users,id',
        ]);

        if ($this->targetUserId === $this->user->id) {
            $this->addError('targetUserId', 'El usuario seleccionado ya es el propietario de este proyecto.');

            return;
        }

        $project = Project::where('id', $this->selectedProjectId)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $targetUser = User::findOrFail($this->targetUserId);

        DB::transaction(function () use ($project, $targetUser) {
            $project->user_id = $targetUser->id;
            $project->save();
        });

        $projectTitle = $project->title;
        $targetName = $targetUser->name;
        $targetEmail = $targetUser->email;

        $this->closeTransferModal();

        session()->flash('status', "El proyecto '{$projectTitle}' se transfirió con éxito a {$targetName} ({$targetEmail}).");
    }

    public function render()
    {
        // Obtener proyectos del usuario solo con metadatos (Cero carga de textos de guión para privacidad estricta)
        $projects = Project::where('user_id', $this->user->id)
            ->select(['id', 'user_id', 'title', 'status', 'created_at'])
            ->withCount('scenes')
            ->orderBy('created_at', 'desc')
            ->get();

        $projectIds = $projects->pluck('id')->toArray();

        // 1. Métricas Cuantitativas de Bloques por Etapa (Agregación puramente numérica sin SELECT de textos)
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

        // 2. Métricas de IA del Proyecto (Peticiones, tokens y costes acumulados)
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

        // 3. Totales acumulados del usuario (Calculados directamente desde agent_request_logs.user_id sin JOINs redundantes)
        $userTotals = DB::table('agent_request_logs')
            ->where('user_id', $this->user->id)
            ->selectRaw('SUM(total_tokens) as total_tokens, SUM(estimated_cost_usd) as total_cost_usd')
            ->first();

        $userTotalTokens = (int) ($userTotals->total_tokens ?? 0);
        $userTotalCost = (float) ($userTotals->total_cost_usd ?? 0.0);

        // Cargar usuarios bajo demanda únicamente cuando el modal de transferencia esté visible (ISSUE-022)
        $availableUsers = $this->showTransferModal
            ? User::where('id', '!=', $this->user->id)
                ->withCount('projects')
                ->orderBy('name')
                ->get()
            : collect();

        return view('livewire.admin.user-detail', [
            'projects' => $projects,
            'blockCountsByProject' => $blockCountsByProject,
            'aiMetricsByProject' => $aiMetricsByProject,
            'userTotalTokens' => $userTotalTokens,
            'userTotalCost' => $userTotalCost,
            'userTokensUsedToday' => $this->user->getTokensUsedToday(),
            'userTokensAvailableToday' => $this->user->getTokensAvailableToday(),
            'userDailyTokenLimit' => $this->user->getDailyTokenLimit(),
            'availableUsers' => $availableUsers,
        ]);
    }
}
