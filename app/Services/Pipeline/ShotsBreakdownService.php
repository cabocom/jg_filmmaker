<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\AgentClientService;
use App\Services\ScriptPipelineService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShotsBreakdownService
{
    public function __construct(
        protected AgentClientService $agentClient,
        protected ?AgentTelemetryService $telemetryService = null
    ) {
        $this->telemetryService = $this->telemetryService ?? app(AgentTelemetryService::class);
    }

    /**
     * @return Collection<int, Shot>
     */
    public function generateShotsBreakdown(ScriptBlock $block, ScriptPipelineService $pipeline): Collection
    {
        $block->loadMissing('scene.project');
        $project = $block->scene?->project;

        if ($block->isStageLocked('shots')) {
            return $block->shots()->orderBy('shot_number')->get();
        }

        $sourceText = ! empty($block->screenplay_content)
            ? $block->screenplay_content
            : (! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text);

        $estimatedTokens = max(50, (int) ceil(mb_strlen((string) $sourceText) / 4) + 150);
        $pipeline->assertUserQuotaAvailable($project, $estimatedTokens);

        $breakdownResult = $this->agentClient->breakdownShots($sourceText);
        $shotsList = $breakdownResult['shots'];

        $createdShots = new Collection;
        $usedShotIds = [];
        $existingBlockShots = $block->shots()->orderBy('shot_number')->get();

        foreach ($shotsList as $index => $shotData) {
            $existingShot = $existingBlockShots->get($index);
            $shot = $this->syncShotEntity($block, $shotData, $existingShot);
            $usedShotIds[] = $shot->id;
            $createdShots->push($shot);
        }

        $this->cleanupObsoleteShots($block, $usedShotIds);
        $this->updateBlockStageToShots($block, $pipeline);

        if ($block->scene) {
            $this->resequenceSceneShots($block->scene);
        }

        if ($project) {
            $this->telemetryService->record(
                projectId: $project->id,
                scriptBlockId: $block->id,
                toolName: 'shot_breakdown_detector',
                inputPayload: ['screenplay' => $sourceText],
                outputPayload: $shotsList,
                telemetry: $this->agentClient->getLastTelemetry()
            );
        }

        return $createdShots;
    }

    protected function syncShotEntity(ScriptBlock $block, array $shotData, ?Shot $existingShot = null): Shot
    {
        $shotType = (string) ($shotData['shot_type'] ?? 'PM');
        $description = (string) ($shotData['description'] ?? '');

        if ($existingShot && $this->hasRecordedTakes($existingShot)) {
            $shot = $existingShot;
        } elseif ($existingShot) {
            $existingShot->update([
                'shot_type' => $shotType,
                'description' => $description,
            ]);
            $shot = $existingShot;
        } else {
            $maxSceneNumber = (int) (Shot::where('scene_id', $block->scene_id)->max('shot_number') ?? 0);
            $shot = Shot::create([
                'scene_id' => $block->scene_id,
                'script_block_id' => $block->id,
                'shot_number' => $maxSceneNumber + 1,
                'shot_type' => $shotType,
                'description' => $description,
            ]);
        }

        if ($shot->wasRecentlyCreated && $shot->takes()->count() === 0) {
            Take::create([
                'shot_id' => $shot->id,
                'take_number' => 1,
                'is_good_take' => false,
                'editorial_notes' => 'Toma inicial generada por pipeline de desglose.',
            ]);
        }

        return $shot;
    }

    /**
     * Resecuencia correlativamente todos los planos de la escena (1..N) ordenados por block_order y orden de plano.
     */
    public function resequenceSceneShots(Scene $scene): void
    {
        DB::transaction(function () use ($scene) {
            Shot::where('scene_id', $scene->id)
                ->lockForUpdate()
                ->pluck('id');

            $shots = Shot::where('shots.scene_id', $scene->id)
                ->leftJoin('script_blocks', 'shots.script_block_id', '=', 'script_blocks.id')
                ->select('shots.*')
                ->orderBy('script_blocks.block_order', 'asc')
                ->orderBy('shots.shot_number', 'asc')
                ->orderBy('shots.created_at', 'asc')
                ->get();

            $num = 1;
            foreach ($shots as $shot) {
                if ($shot->shot_number !== $num) {
                    $shot->update(['shot_number' => $num]);
                }
                $num++;
            }
        });
    }

    protected function hasRecordedTakes(Shot $shot): bool
    {
        return $shot->takes()
            ->where(fn ($q) => $q->whereNotNull('file_path')->orWhere('is_good_take', true)->orWhere('editorial_notes', '!=', 'Toma inicial generada por pipeline de desglose.'))
            ->exists();
    }

    protected function cleanupObsoleteShots(ScriptBlock $block, array $usedShotIds): void
    {
        $block->shots()->whereNotIn('id', $usedShotIds)->each(function (Shot $obsoleteShot) {
            if (! $this->hasRecordedTakes($obsoleteShot)) {
                $obsoleteShot->takes()->delete();
                $obsoleteShot->delete();
            }
        });
    }

    protected function updateBlockStageToShots(ScriptBlock $block, ScriptPipelineService $pipeline): void
    {
        DB::transaction(function () use ($block, $pipeline) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
            if (! $fresh) {
                return;
            }

            $freshLocks = $pipeline->resolveUserLocks($fresh);
            unset($freshLocks['ai_telemetry']);

            $fresh->update([
                'user_locks' => $freshLocks,
                'stage' => 'shots',
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);
        });
    }
}
