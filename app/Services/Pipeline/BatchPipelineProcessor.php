<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Exceptions\QuotaExceededException;
use App\Models\ScriptBlock;
use App\Services\ScriptPipelineService;

class BatchPipelineProcessor
{
    /**
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchStructureBlocks(iterable $blocks, ScriptPipelineService $pipeline): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($blocks as $block) {
            if ($block->isStageLocked('structured') || empty(trim((string) $block->raw_text))) {
                $skipped++;

                continue;
            }

            try {
                $pipeline->transitionToStructured($block);
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
    public function batchFormatBlocksToScreenplay(iterable $blocks, ScriptPipelineService $pipeline): array
    {
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
                $pipeline->transitionToScreenplay($block);
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
        $resStructured = $this->batchStructureBlocks($blocks, $pipeline);
        $freshBlocks = collect($blocks)->map(fn ($b) => $b->fresh())->filter();
        $resScreenplay = $this->batchFormatBlocksToScreenplay($freshBlocks, $pipeline);
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
