<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Exceptions\QuotaExceededException;
use App\Models\ScriptBlock;
use App\Services\ScriptPipelineService;

class BatchPipelineProcessor
{
    /**
     * Sincroniza personajes y localizaciones una única vez para los proyectos del lote.
     *
     * @param  iterable<ScriptBlock>  $blocks
     */
    protected function syncBatchProjectsContext(iterable $blocks, ScriptPipelineService $pipeline): void
    {
        $projects = collect($blocks)->map(fn ($b) => $b->scene?->project)->filter()->unique('id');
        foreach ($projects as $project) {
            $pipeline->syncProjectCharacters($project);
            $pipeline->syncProjectLocations($project);
        }
    }

    /**
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchStructureBlocks(iterable $blocks, ScriptPipelineService $pipeline, bool $syncProjectContext = true): array
    {
        if ($syncProjectContext) {
            $this->syncBatchProjectsContext($blocks, $pipeline);
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($blocks as $block) {
            if ($block->isStageLocked('structured') || empty(trim((string) $block->raw_text))) {
                $skipped++;

                continue;
            }

            try {
                $pipeline->transitionToStructured($block, syncProjectContext: false);
                $processed++;
            } catch (QuotaExceededException $e) {
                $errors[] = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                $errors[] = "Bloque {$block->block_order}: {$e->getMessage()}";
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchFormatBlocksToScreenplay(iterable $blocks, ScriptPipelineService $pipeline, bool $syncProjectContext = true): array
    {
        if ($syncProjectContext) {
            $this->syncBatchProjectsContext($blocks, $pipeline);
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($blocks as $block) {
            $sourceText = ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text;
            if ($block->isStageLocked('screenplay') || empty(trim((string) $sourceText))) {
                $skipped++;

                continue;
            }

            try {
                $pipeline->transitionToScreenplay($block, syncProjectContext: false);
                $processed++;
            } catch (QuotaExceededException $e) {
                $errors[] = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                $errors[] = "Bloque {$block->block_order}: {$e->getMessage()}";
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchGenerateShots(iterable $blocks, ScriptPipelineService $pipeline): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($blocks as $block) {
            if ($block->isStageLocked('shots') || empty(trim((string) $block->screenplay_content))) {
                $skipped++;

                continue;
            }

            try {
                $pipeline->generateShotsBreakdown($block);
                $processed++;
            } catch (QuotaExceededException $e) {
                $errors[] = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                $errors[] = "Bloque {$block->block_order}: {$e->getMessage()}";
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{structured: int, screenplay: int, shots: int, errors: list<string>}
     */
    public function batchRunFullPipeline(iterable $blocks, ScriptPipelineService $pipeline): array
    {
        $blockList = collect($blocks);
        $this->syncBatchProjectsContext($blockList, $pipeline);

        $resStructured = $this->batchStructureBlocks($blockList, $pipeline, syncProjectContext: false);
        $freshBlocks = $blockList->map(fn ($b) => $b->fresh())->filter();
        $resScreenplay = $this->batchFormatBlocksToScreenplay($freshBlocks, $pipeline, syncProjectContext: false);
        $freshBlocks = $freshBlocks->map(fn ($b) => $b->fresh())->filter();
        $resShots = $this->batchGenerateShots($freshBlocks, $pipeline);

        return [
            'structured' => $resStructured['processed'],
            'screenplay' => $resScreenplay['processed'],
            'shots' => $resShots['processed'],
            'errors' => array_merge($resStructured['errors'], $resScreenplay['errors'], $resShots['errors']),
        ];
    }
}
