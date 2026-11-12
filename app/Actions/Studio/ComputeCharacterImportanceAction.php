<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Character;
use App\Models\Project;
use App\Models\ScriptBlock;

final readonly class ComputeCharacterImportanceAction
{
    /**
     * Calcula las intervenciones de diálogo por personaje en todo el proyecto.
     *
     * @return array<string, int>
     */
    public function computeInterventions(Project $project): array
    {
        $blocks = ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $project->id))
            ->get(['screenplay_content', 'structured_text', 'raw_text']);

        $counts = [];
        $ignoredKeywords = ['INT', 'EXT', 'INT.', 'EXT.', 'INT/EXT', 'CORTE A', 'DISUELVE A', 'FADE IN', 'FADE OUT'];

        foreach ($blocks as $block) {
            $text = $block->screenplay_content ?: ($block->structured_text ?: $block->raw_text);
            if (! $text) {
                continue;
            }

            if (preg_match_all('/^[A-ZÁÉÍÓÚÑ0-9\s\.\-\(\)]+$/m', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $cleaned = trim((string) preg_replace('/\s*\(.*?\)\s*/', '', $match));
                    $upper = mb_strtoupper($cleaned);
                    if ($upper !== '' && ! in_array($upper, $ignoredKeywords, true)) {
                        $counts[$upper] = ($counts[$upper] ?? 0) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Resuelve el nivel de importancia del personaje (manual o automático según intervenciones).
     *
     * @param  array<string, int>  $counts
     * @return array{level: string, count: int, is_auto: bool}
     */
    public function resolveImportance(Character $character, array $counts): array
    {
        $custom = is_array($character->custom_attributes) ? $character->custom_attributes : [];
        $explicit = $custom['importance'] ?? null;
        $count = $counts[mb_strtoupper(trim($character->name))] ?? 0;

        if ($explicit) {
            return [
                'level' => (string) $explicit,
                'count' => $count,
                'is_auto' => false,
            ];
        }

        if ($count >= 10) {
            $level = 'protagonista';
        } elseif ($count >= 3) {
            $level = 'secundario';
        } else {
            $level = 'reparto';
        }

        return [
            'level' => $level,
            'count' => $count,
            'is_auto' => true,
        ];
    }
}
