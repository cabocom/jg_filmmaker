<?php

declare(strict_types=1);

namespace App\Services\Tts\Concerns;

use App\Models\Project;
use App\Models\ScriptBlock;
use Illuminate\Database\Eloquent\Collection;

trait WithTtsManifestDelegation
{
    public function parseBlockToSegments(
        ScriptBlock $block,
        Collection|array $characters = [],
        ?Project $project = null,
        bool $persistBibleChanges = false
    ): array {
        return $this->segmenter->parseBlockToSegments($block, $characters, $project, $persistBibleChanges);
    }

    public function buildManifestForScope(
        Project $project,
        ?string $sceneId = null,
        ?string $blockId = null,
        bool $persistBibleChanges = false
    ): array {
        return $this->manifestService->buildManifestForScope($project, $sceneId, $blockId, $persistBibleChanges);
    }

    public function isAudioCached(string $text, ?string $voice = null, float $speed = 1.0): bool
    {
        return $this->manifestService->isAudioCached($text, $voice, $speed);
    }

    public function isProjectAudioComplete(Project $project, float $speed = 1.0): bool
    {
        return $this->manifestService->isProjectAudioComplete($project, $speed);
    }

    public function invalidateAudioCompleteCache(Project|string $project): void
    {
        $this->manifestService->invalidateAudioCompleteCache($project);
    }

    public function exportProjectToMp3(Project $project, float $speed = 1.0): string
    {
        return $this->manifestService->exportProjectToMp3($project, $speed);
    }

    public function prewarmManifest(array $manifest, float $speed = 1.0, ?callable $onProgress = null, ?Project $project = null): array
    {
        return $this->manifestService->prewarmManifest($manifest, $speed, $onProgress, $project);
    }
}
