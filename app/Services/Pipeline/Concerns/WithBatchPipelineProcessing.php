<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Concerns;

trait WithBatchPipelineProcessing
{
    public function batchStructureBlocks(iterable $blocks, bool $syncProjectContext = true): array
    {
        return $this->batchProcessor->batchStructureBlocks($blocks, $this, $syncProjectContext);
    }

    public function batchFormatBlocksToScreenplay(iterable $blocks, bool $syncProjectContext = true): array
    {
        return $this->batchProcessor->batchFormatBlocksToScreenplay($blocks, $this, $syncProjectContext);
    }

    public function batchGenerateShots(iterable $blocks): array
    {
        return $this->batchProcessor->batchGenerateShots($blocks, $this);
    }

    public function batchRunFullPipeline(iterable $blocks): array
    {
        return $this->batchProcessor->batchRunFullPipeline($blocks, $this);
    }
}
