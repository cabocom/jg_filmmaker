<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class GetProjectAiTelemetrySummaryQuery
{
    /**
     * @param  array<int, string>  $projectIds
     * @return array<string, array{total_requests: int, total_tokens: int, total_cost_usd: float}>
     */
    public function getForProjects(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $rows = DB::table('agent_request_logs')
            ->whereIn('project_id', $projectIds)
            ->select(
                'project_id',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as total_cost_usd')
            )
            ->groupBy('project_id')
            ->get();

        $metrics = [];
        foreach ($rows as $row) {
            $metrics[$row->project_id] = [
                'total_requests' => (int) $row->total_requests,
                'total_tokens' => (int) $row->total_tokens,
                'total_cost_usd' => (float) $row->total_cost_usd,
            ];
        }

        return $metrics;
    }

    /**
     * @param  array<int, string>  $projectIds
     * @return array<string, array<string, int>>
     */
    public function getBlockCountsByStage(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $rows = DB::table('script_blocks')
            ->join('scenes', 'script_blocks.scene_id', '=', 'scenes.id')
            ->whereIn('scenes.project_id', $projectIds)
            ->select('scenes.project_id', 'script_blocks.stage', DB::raw('COUNT(*) as total'))
            ->groupBy('scenes.project_id', 'script_blocks.stage')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->project_id][$row->stage] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $userIds
     */
    public function getForUsers(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return DB::table('agent_request_logs')
            ->whereIn('user_id', $userIds)
            ->select(
                'user_id',
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as total_cost_usd')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }
}
