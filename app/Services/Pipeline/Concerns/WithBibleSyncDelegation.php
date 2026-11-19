<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Concerns;

use App\Models\Project;

trait WithBibleSyncDelegation
{
    public function syncProjectCharacters(Project $project): void
    {
        $this->bibleSync->syncProjectCharacters($project);
    }

    public function inferCharactersFromText(string $text, array $existingCharacters = [], ?Project $project = null, bool $allowLlmExtraction = true): array
    {
        return $this->bibleSync->inferCharactersFromText($text, $existingCharacters, $project, $allowLlmExtraction);
    }

    public function extractDeterministicCandidates(string $text, array $existingCharacters = []): array
    {
        return $this->bibleSync->extractDeterministicCandidates($text, $existingCharacters);
    }

    public function capitalizeCharacterNames(string $text, array $characters, string $stage = 'structured'): string
    {
        return $this->bibleSync->capitalizeCharacterNames($text, $characters, $stage);
    }

    public function parseSlugline(string $slugline): array
    {
        return $this->sluglineSync->parseSlugline($slugline);
    }

    public function syncProjectLocations(Project $project): void
    {
        $this->sluglineSync->syncProjectLocations($project);
    }
}
