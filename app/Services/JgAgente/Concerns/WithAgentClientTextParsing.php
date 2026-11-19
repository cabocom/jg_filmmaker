<?php

declare(strict_types=1);

namespace App\Services\JgAgente\Concerns;

trait WithAgentClientTextParsing
{
    /**
     * Parsea planos anotados con el delimitador canónico '--- shot N (tipo) ---'.
     *
     * @return array<int, array{shot_number: int, shot_type: string, description: string}>
     */
    public function parseShotsFromText(string $text): array
    {
        return $this->storyboardClient->parseShotsFromText($text);
    }

    /**
     * Aplica los candados de usuario (pinned_terms y manual_diffs) a un texto.
     *
     * @param  array<string, mixed>  $userLocks
     */
    public function applyUserLocks(string $text, array $userLocks, string $stage): string
    {
        return $this->scriptClient->applyUserLocks($text, $userLocks, $stage);
    }
}
