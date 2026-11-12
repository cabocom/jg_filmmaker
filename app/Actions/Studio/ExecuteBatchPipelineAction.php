<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Models\ScriptBlock;
use App\Models\User;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Collection;

final readonly class ExecuteBatchPipelineAction
{
    /**
     * Calcula la estimación de tokens requeridos para el lote seleccionado.
     */
    public function estimateTokens(Project $project, string $scope, string $stage, ?string $activeSceneId): int
    {
        $blocks = $this->resolveBlocks($project, $scope, $activeSceneId);
        $totalEst = 0;

        foreach ($blocks as $block) {
            $text = match ($stage) {
                'structured' => (string) ($block->raw_text ?? ''),
                'screenplay' => (string) ($block->structured_text ?: $block->raw_text ?? ''),
                'shots' => (string) ($block->screenplay_content ?: $block->structured_text ?? ''),
                'all' => (string) ($block->raw_text ?? ''),
                default => (string) ($block->raw_text ?? ''),
            };

            $chars = strlen($text);
            $tokens = (int) ceil($chars / 4);

            $multiplier = match ($stage) {
                'structured' => 2,
                'screenplay' => 2.5,
                'shots' => 1.8,
                'all' => 5.5,
                default => 2,
            };

            $totalEst += (int) ceil($tokens * $multiplier) + 80;
        }

        return max(50, $totalEst);
    }

    /**
     * Ejecuta el pipeline en lote según el alcance y etapa especificados.
     *
     * @return array{success: bool, flash_type: string, message: string, activity_log: ?array{action: string, entity_type: string, entity_id: string, description: string, details: array<string, mixed>}}
     */
    public function execute(
        Project $project,
        string $scope,
        string $stage,
        ?string $activeSceneId,
        int $estimatedTokens,
        ?User $user = null,
        ?ScriptPipelineService $pipelineService = null
    ): array {
        $quotaError = $this->verifyTokenQuota($user ?? auth()->user() ?? $project->user, $estimatedTokens);
        if ($quotaError !== null) {
            return $quotaError;
        }

        $blocks = $this->resolveBlocks($project, $scope, $activeSceneId);
        $scopeDesc = $this->resolveScopeDescription($project, $scope, $activeSceneId, count($blocks));
        if ($blocks->isEmpty()) {
            return ['success' => false, 'flash_type' => 'error', 'message' => "No hay bloques de texto disponibles para procesar en {$scopeDesc}.", 'activity_log' => null];
        }

        try {
            $res = $this->dispatchBatchStage($stage, $blocks, $pipelineService ?? app(ScriptPipelineService::class));

            return $this->buildSuccessResult($project, $stage, $scope, $scopeDesc, $blocks, $res);
        } catch (\Throwable $e) {
            return ['success' => false, 'flash_type' => 'error', 'message' => "Error en el procesamiento en lote: {$e->getMessage()}", 'activity_log' => null];
        }
    }

    private function verifyTokenQuota(?User $user, int $estimatedTokens): ?array
    {
        if (! $user || $user->hasDailyTokensAvailable($estimatedTokens)) {
            return null;
        }

        $available = $user->getTokensAvailableToday();
        $limit = $user->getDailyTokenLimit();

        return [
            'success' => false,
            'flash_type' => 'error',
            'message' => "Saldo insuficiente de tokens para procesar en lote ({$available} disponibles, ~{$estimatedTokens} requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará a las 00:00.',
            'activity_log' => null,
        ];
    }

    private function dispatchBatchStage(string $stage, Collection $blocks, ScriptPipelineService $svc): array
    {
        return match ($stage) {
            'structured' => $svc->batchStructureBlocks($blocks),
            'screenplay' => $svc->batchFormatBlocksToScreenplay($blocks),
            'shots' => $svc->batchGenerateShots($blocks),
            default => $svc->batchRunFullPipeline($blocks),
        };
    }

    private function buildSuccessResult(Project $project, string $stage, string $scope, string $scopeDesc, Collection $blocks, array $res): array
    {
        $msg = $this->buildSuccessMessage($stage, $scopeDesc, $res);
        $hasErrors = ! empty($res['errors']);

        return [
            'success' => true,
            'flash_type' => $hasErrors ? 'error' : 'success',
            'message' => $hasErrors ? $msg.' Se produjeron advertencias: '.implode(' | ', array_slice($res['errors'], 0, 3)) : $msg,
            'activity_log' => [
                'action' => 'batch_pipeline_execution',
                'entity_type' => 'Project',
                'entity_id' => $project->id,
                'description' => $msg,
                'details' => [
                    'scope' => $scope,
                    'stage' => $stage,
                    'blocks_total' => count($blocks),
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, ScriptBlock>
     */
    private function resolveBlocks(Project $project, string $scope, ?string $activeSceneId): Collection
    {
        if ($scope === 'scene') {
            $scene = $activeSceneId
                ? $project->scenes()->whereKey($activeSceneId)->first()
                : $project->scenes()->first();

            return $scene ? $scene->scriptBlocks()->orderBy('block_order')->get() : collect();
        }

        return ScriptBlock::whereIn('scene_id', $project->scenes()->pluck('id'))
            ->orderBy('scene_id')
            ->orderBy('block_order')
            ->get();
    }

    private function resolveScopeDescription(Project $project, string $scope, ?string $activeSceneId, int $count): string
    {
        if ($scope === 'scene') {
            $scene = $activeSceneId
                ? $project->scenes()->whereKey($activeSceneId)->first()
                : $project->scenes()->first();

            return $scene ? "Escena #{$scene->scene_order} ({$scene->slugline})" : 'Escena no encontrada';
        }

        return "Proyecto Completo ({$count} bloques)";
    }

    /**
     * @param  array<string, mixed>  $res
     */
    private function buildSuccessMessage(string $stage, string $scopeDesc, array $res): string
    {
        return match ($stage) {
            'structured' => "Procesamiento por lote a Tratamiento finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos (protegidos o vacíos).",
            'screenplay' => "Procesamiento por lote a Guion Literario finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos.",
            'shots' => "Desglose por lote a Planos Técnicos finalizado en {$scopeDesc}: {$res['processed']} procesados, {$res['skipped']} omitidos.",
            default => "Pipeline Completo ejecutado en {$scopeDesc}: {$res['structured']} estructurados, {$res['screenplay']} formateados a guion, {$res['shots']} desglosados a planos.",
        };
    }
}
