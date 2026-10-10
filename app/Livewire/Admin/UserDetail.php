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

    public function mount(User $user): void
    {
        $this->user = $user;
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

        // 3. Totales acumulados del usuario
        $userTotalTokens = DB::table('agent_request_logs')
            ->join('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->where('projects.user_id', $this->user->id)
            ->sum('agent_request_logs.total_tokens') ?: 0;

        $userTotalCost = DB::table('agent_request_logs')
            ->join('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->where('projects.user_id', $this->user->id)
            ->sum('agent_request_logs.estimated_cost_usd') ?: 0.0;

        return view('livewire.admin.user-detail', [
            'projects' => $projects,
            'blockCountsByProject' => $blockCountsByProject,
            'aiMetricsByProject' => $aiMetricsByProject,
            'userTotalTokens' => (int) $userTotalTokens,
            'userTotalCost' => (float) $userTotalCost,
        ]);
    }
}
