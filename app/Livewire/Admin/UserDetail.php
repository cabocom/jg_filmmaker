<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\TransferProjectOwnershipAction;
use App\Models\Project;
use App\Models\User;
use App\Queries\Admin\GetProjectAiTelemetrySummaryQuery;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Auditoría de Usuario - Administración jG Filmmaker')]
class UserDetail extends Component
{
    use WithPagination;

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
        $this->authorize('admin');

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

    public function transferProject(?TransferProjectOwnershipAction $action = null): void
    {
        $this->authorize('admin');

        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'targetUserId' => 'required|exists:users,id',
        ]);

        if ($this->targetUserId === $this->user->id) {
            $this->addError('targetUserId', 'El usuario seleccionado ya es el propietario de este proyecto.');

            return;
        }

        try {
            $action ??= app(TransferProjectOwnershipAction::class);
            $project = $action->execute($this->selectedProjectId, $this->targetUserId);
            $targetUser = User::findOrFail($this->targetUserId);

            $this->closeTransferModal();
            session()->flash('status', "El proyecto '{$project->title}' se transfirió con éxito a {$targetUser->name} ({$targetUser->email}).");
        } catch (\InvalidArgumentException $e) {
            $this->addError('targetUserId', $e->getMessage());
        }
    }

    public function render()
    {
        // Obtener proyectos del usuario solo con metadatos paginados (Cero carga de textos de guión para privacidad estricta)
        $projects = Project::where('user_id', $this->user->id)
            ->select(['id', 'user_id', 'title', 'status', 'created_at'])
            ->withCount('scenes')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $projectIds = $projects->pluck('id')->toArray();

        $telemetryQuery = app(GetProjectAiTelemetrySummaryQuery::class);
        $blockCountsByProject = $telemetryQuery->getBlockCountsByStage($projectIds);
        $aiMetricsByProject = $telemetryQuery->getForProjects($projectIds);

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
