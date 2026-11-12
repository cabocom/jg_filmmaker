<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AnalyzePlotHolesAction
{
    /**
     * Audita todas las etapas del proyecto en busca de inconsistencias dramáticas.
     *
     * @return array{status: string, flash_type: string, message: string, activity_log: ?array{action: string, entity_type: string, entity_id: string, description: string, details: array<string, mixed>}}
     */
    public function execute(Project $project, ?User $user = null, ?AgentClientService $agentClient = null): array
    {
        $agentClient ??= app(AgentClientService::class);
        $user ??= auth()->user() ?? $project->user;

        if ($user && ! $user->hasDailyTokensAvailable(50)) {
            return ['status' => 'no_tokens', 'flash_type' => 'error', 'message' => 'Has alcanzado tu cupo diario de tokens de IA ('.number_format($user->getDailyTokenLimit(), 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.', 'activity_log' => null];
        }

        $scenes = $project->scenes()->with(['scriptBlocks' => fn ($q) => $q->orderBy('block_order')])->orderBy('scene_order')->get();
        $allBlocks = $scenes->flatMap(fn ($s) => $s->scriptBlocks);

        if ($allBlocks->isEmpty()) {
            return ['status' => 'no_blocks', 'flash_type' => 'warning', 'message' => 'No hay bloques de guión en el proyecto para auditar.', 'activity_log' => null];
        }

        $earliestHoleByBlockId = $this->auditAllStages($project, $scenes, $allBlocks, $user, $agentClient);
        $stageCounts = $this->persistHolesToBlocks($allBlocks, $earliestHoleByBlockId);

        return $this->buildResultPayload($project, $allBlocks, $earliestHoleByBlockId, $stageCounts, array_sum($stageCounts));
    }

    private function auditAllStages(Project $project, Collection $scenes, Collection $allBlocks, ?User $user, AgentClientService $agentClient): array
    {
        $characters = $project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray();
        $locations = $project->locations()->get()->map(fn (Location $l) => $l->toAgentContext())->toArray();
        $stages = ['raw' => 'Idea (Bruto)', 'structured' => 'Tratamiento', 'screenplay' => 'Guion'];
        $earliest = [];

        foreach ($stages as $stage => $stageLabel) {
            $data = $this->collectStageBlocks($scenes, $stage);
            if (empty($data)) {
                continue;
            }

            $est = max(50, (int) ceil(strlen(json_encode($data)) / 4) + 150);
            if ($user && ! $user->hasDailyTokensAvailable($est)) {
                break;
            }

            $res = $agentClient->auditPlotHoles($data, $characters, $locations);
            app(AgentTelemetryService::class)->record($project->id, null, 'plot_hole_detector', $data, $res, $agentClient->getLastTelemetry(), $user?->id);
            $this->mergeDetectedHoles($earliest, (array) ($res['detected_holes'] ?? []), $allBlocks, $stage, $stageLabel);
        }

        return $earliest;
    }

    /**
     * Limpia todas las marcas de agujeros de guion de los bloques del proyecto.
     */
    public function clearPlotHoles(Project $project): void
    {
        foreach ($project->scenes as $scene) {
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
    }

    /**
     * @param  Collection<int, Scene>  $scenes
     * @return list<array<string, mixed>>
     */
    private function collectStageBlocks(Collection $scenes, string $stage): array
    {
        $blocksData = [];
        foreach ($scenes as $sc) {
            foreach ($sc->scriptBlocks as $b) {
                $text = match ($stage) {
                    'raw' => (string) ($b->raw_text ?? ''),
                    'structured' => (string) ($b->structured_text ?? ''),
                    'screenplay' => (string) ($b->screenplay_content ?? ''),
                };
                if (blank($text)) {
                    continue;
                }
                $blocksData[] = [
                    'id' => (string) $b->id,
                    'scene_id' => (string) $sc->id,
                    'scene_order' => $sc->scene_order,
                    'scene_slugline' => $sc->slugline,
                    'block_order' => $b->block_order,
                    'stage' => $stage,
                    'raw_text' => (string) ($b->raw_text ?? ''),
                    'structured_text' => (string) ($b->structured_text ?? ''),
                    'screenplay_content' => (string) ($b->screenplay_content ?? ''),
                    'text' => $text,
                ];
            }
        }

        return $blocksData;
    }

    /**
     * @param  array<string, array{reason: string, stage: string, stage_label: string}>  $earliest
     * @param  list<array<string, mixed>>  $detectedHoles
     * @param  Collection<int, ScriptBlock>  $allBlocks
     */
    private function mergeDetectedHoles(array &$earliest, array $detectedHoles, $allBlocks, string $stage, string $stageLabel): void
    {
        foreach ($detectedHoles as $hole) {
            $blockId = (string) ($hole['block_id'] ?? '');
            $reason = trim((string) ($hole['reason'] ?? ''));
            if (empty($blockId) || empty($reason) || isset($earliest[$blockId])) {
                continue;
            }

            $targetBlock = $allBlocks->firstWhere('id', $blockId);
            if ($targetBlock && $this->isHoleJustified($targetBlock, $reason)) {
                continue;
            }

            $earliest[$blockId] = ['reason' => $reason, 'stage' => $stage, 'stage_label' => $stageLabel];
        }
    }

    private function isHoleJustified(ScriptBlock $block, string $reason): bool
    {
        foreach ($block->getJustifiedPlotHoles() as $j) {
            $jReason = trim((string) ($j['hole_reason'] ?? $j['original_reason'] ?? ''));
            if (! empty($jReason) && (str_contains(mb_strtolower($reason), mb_strtolower($jReason)) || str_contains(mb_strtolower($jReason), mb_strtolower($reason)))) {
                return true;
            }
        }

        return false;
    }

    private function persistHolesToBlocks(Collection $allBlocks, array $earliest): array
    {
        $stageCounts = ['raw' => 0, 'structured' => 0, 'screenplay' => 0];
        foreach ($allBlocks as $block) {
            $holeInfo = $earliest[$block->id] ?? null;
            DB::transaction(function () use ($block, $holeInfo) {
                /** @var ScriptBlock|null $fresh */
                $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
                if (! $fresh) {
                    return;
                }
                $locks = $fresh->user_locks ?? [];
                if ($holeInfo !== null) {
                    $locks['plot_hole_stage'] = $holeInfo['stage'];
                    $fresh->update(['plot_hole' => $holeInfo['reason'], 'user_locks' => $locks, 'lock_version' => ($fresh->lock_version ?? 1) + 1]);
                } else {
                    unset($locks['plot_hole_stage']);
                    $fresh->update(['plot_hole' => null, 'user_locks' => $locks, 'lock_version' => ($fresh->lock_version ?? 1) + 1]);
                }
            });

            if ($holeInfo !== null) {
                $stageCounts[$holeInfo['stage']]++;
            }
        }

        return $stageCounts;
    }

    /**
     * @param  Collection<int, ScriptBlock>  $allBlocks
     * @param  array<string, array{reason: string, stage: string, stage_label: string}>  $earliest
     * @param  array<string, int>  $stageCounts
     * @return array{status: string, flash_type: string, message: string, activity_log: ?array{action: string, entity_type: string, entity_id: string, description: string, details: array<string, mixed>}}
     */
    private function buildResultPayload(Project $project, Collection $allBlocks, array $earliest, array $stageCounts, int $totalMarked): array
    {
        $detectedHolesDetailed = $this->buildHolesDetailsList($allBlocks, $earliest);

        if ($totalMarked > 0) {
            return $this->buildHolesFoundResult($project, $allBlocks, $stageCounts, $totalMarked, $detectedHolesDetailed);
        }

        return $this->buildNoHolesResult($project, $allBlocks, $stageCounts);
    }

    private function buildHolesFoundResult(Project $project, Collection $allBlocks, array $stageCounts, int $totalMarked, array $detailed): array
    {
        $detailsStr = $this->formatStageSummary($stageCounts);

        return [
            'status' => 'holes_found',
            'flash_type' => 'warning',
            'message' => "Se han detectado {$totalMarked} posibles agujeros de guión ({$detailsStr}). Marcado el origen más temprano.",
            'activity_log' => [
                'action' => 'analyze_plot_holes',
                'entity_type' => 'Project',
                'entity_id' => $project->id,
                'description' => "Auditoría de agujeros de guión ejecutada ({$totalMarked} detectados: {$detailsStr})",
                'details' => [
                    'total_detected' => $totalMarked,
                    'total_blocks_audited' => $allBlocks->count(),
                    'by_stage' => $stageCounts,
                    'detected_holes' => $detailed,
                ],
            ],
        ];
    }

    private function buildNoHolesResult(Project $project, Collection $allBlocks, array $stageCounts): array
    {
        return [
            'status' => 'no_holes',
            'flash_type' => 'success',
            'message' => 'Filtro de agujeros de guión: No se han detectado incongruencias en ninguna de las etapas.',
            'activity_log' => [
                'action' => 'analyze_plot_holes',
                'entity_type' => 'Project',
                'entity_id' => $project->id,
                'description' => 'Auditoría de agujeros de guión ejecutada: sin inconsistencias detectadas',
                'details' => [
                    'total_detected' => 0,
                    'total_blocks_audited' => $allBlocks->count(),
                    'by_stage' => $stageCounts,
                    'detected_holes' => [],
                ],
            ],
        ];
    }

    private function buildHolesDetailsList(Collection $allBlocks, array $earliest): array
    {
        $details = [];
        foreach ($earliest as $bId => $hInfo) {
            $b = $allBlocks->firstWhere('id', $bId);
            $sc = $b?->scene;
            $text = trim(($b?->raw_text ?? '').' '.($b?->structured_text ?? '').' '.($b?->screenplay_content ?? ''));
            $details[] = [
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

        return $details;
    }

    private function formatStageSummary(array $stageCounts): string
    {
        $details = [];
        foreach (['raw' => 'Idea', 'structured' => 'Tratamiento', 'screenplay' => 'Guion'] as $st => $lbl) {
            if ($stageCounts[$st] > 0) {
                $details[] = "{$stageCounts[$st]} en {$lbl}";
            }
        }

        return implode(', ', $details);
    }
}
