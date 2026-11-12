<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\ScriptBlock;
use Illuminate\Support\Str;

final readonly class JustifyPlotHoleAction
{
    /**
     * Justifica un agujero de guion y lo traslada a la lista de justificados del bloque.
     *
     * @return array{action: string, entity_type: string, entity_id: string, description: string, details: array<string, mixed>}
     */
    public function justify(ScriptBlock $block, string $justificationText, ?string $holeReason = null): array
    {
        $reason = $holeReason ?: (string) $block->plot_hole;
        $stage = $block->getPlotHoleStage() ?? 'raw';
        $stageLabel = $block->getPlotHoleStageLabel();
        $trimmed = trim($justificationText);

        $block->mutateUserLocks(function (array $userLocks, ScriptBlock $fresh) use ($reason, $stage, $stageLabel, $trimmed) {
            $justifiedHoles = (array) ($userLocks['justified_plot_holes'] ?? []);
            $justifiedHoles[] = [
                'id' => Str::uuid()->toString(),
                'hole_reason' => $reason,
                'original_reason' => $reason,
                'stage' => $stage,
                'stage_label' => $stageLabel,
                'justification' => $trimmed,
                'justified_at' => now()->toIso8601String(),
            ];
            unset($userLocks['plot_hole_stage']);
            $userLocks['justified_plot_holes'] = $justifiedHoles;
            $fresh->plot_hole = null;

            return $userLocks;
        });

        return $this->buildAuditPayload($block, $reason, $stage, $stageLabel, $trimmed);
    }

    /**
     * @return array{action: string, entity_type: string, entity_id: string, description: string, details: array<string, mixed>}
     */
    private function buildAuditPayload(ScriptBlock $block, string $reason, string $stage, string $stageLabel, string $trimmed): array
    {
        $scene = $block->scene;

        return [
            'action' => 'justify_plot_hole',
            'entity_type' => 'ScriptBlock',
            'entity_id' => $block->id,
            'description' => "Agujero de guión justificado en Bloque #{$block->block_order} (Escena #{$scene?->scene_order}): \"".Str::limit($trimmed, 80).'"',
            'details' => [
                'block_id' => $block->id,
                'scene_id' => $scene?->id,
                'scene_order' => $scene?->scene_order,
                'scene_slugline' => $scene?->slugline,
                'block_order' => $block->block_order,
                'hole_reason' => $reason,
                'stage' => $stage,
                'stage_label' => $stageLabel,
                'justification' => $trimmed,
                'raw_text' => $block->raw_text,
            ],
        ];
    }
}
