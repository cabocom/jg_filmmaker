<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Services\AgentTelemetryService;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Cache;

final readonly class AuditBibleEntityUsageAction
{
    /**
     * Audita deterministamente las apariciones de un personaje en escenas y bloques.
     *
     * @return list<string>
     */
    public function auditCharacterUsages(Project $project, Character $char, ?AgentClientService $agentClient = null): array
    {
        $cacheKey = "project:{$project->id}:char_audit:{$char->id}";

        return Cache::remember($cacheKey, 30, function () use ($project, $char, $agentClient): array {
            $charName = trim($char->name);
            $scenes = $project->scenes()->with('scriptBlocks')->orderBy('scene_order')->get();
            $agentClient ??= app(AgentClientService::class);

            $candidateBlocks = [];
            $candidateSnippets = [];
            $candidateBlockIds = [];

            foreach ($scenes as $scene) {
                foreach ($scene->scriptBlocks as $block) {
                    $texts = [
                        $block->screenplay_content ?? '',
                        $block->structured_text ?? '',
                        $block->raw_text ?? '',
                    ];

                    foreach ($texts as $text) {
                        if (! empty($text) && preg_match('/\b'.preg_quote($charName, '/').'\b/iu', (string) $text)) {
                            $candidateBlocks[] = "ESC. {$scene->scene_order} · BLOQUE {$block->block_order}";
                            $candidateSnippets[] = (string) $text;
                            $candidateBlockIds[] = $block->id;
                            break;
                        }
                    }
                }
            }

            if (empty($candidateBlocks)) {
                return [];
            }

            $snippetsBatch = array_slice($candidateSnippets, 0, 10);
            $audit = $agentClient->auditCharacterUsage($charName, $snippetsBatch);

            app(AgentTelemetryService::class)->record(
                projectId: $project->id,
                scriptBlockId: $candidateBlockIds[0] ?? null,
                toolName: 'audit_character_usage',
                inputPayload: ['character_name' => $charName, 'scene_texts' => $snippetsBatch],
                outputPayload: $audit,
                telemetry: $agentClient->getLastTelemetry(),
                userId: $project->user_id
            );

            if (! ($audit['in_use'] ?? true)) {
                return [];
            }

            return array_values(array_unique($candidateBlocks));
        });
    }

    /**
     * Audita deterministamente las apariciones de una localización en escenas y bloques.
     *
     * @return list<string>
     */
    public function auditLocationUsages(Project $project, Location $loc): array
    {
        $locName = trim($loc->name);
        $usages = [];
        $scenes = $project->scenes()->with('scriptBlocks')->orderBy('scene_order')->get();

        foreach ($scenes as $scene) {
            if ($scene->location_id === $loc->id) {
                $usages[] = "ESC. {$scene->scene_order} (Asignada a la escena)";
            } elseif (! empty($scene->slugline) && preg_match('/\b'.preg_quote($locName, '/').'\b/iu', $scene->slugline)) {
                $usages[] = "ESC. {$scene->scene_order} (Encabezado de escena)";
            }

            foreach ($scene->scriptBlocks as $block) {
                $texts = [
                    $block->screenplay_content ?? '',
                    $block->structured_text ?? '',
                    $block->raw_text ?? '',
                ];

                $found = false;
                foreach ($texts as $text) {
                    if (! empty($text) && preg_match('/\b'.preg_quote($locName, '/').'\b/iu', (string) $text)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $usages[] = "ESC. {$scene->scene_order} · BLOQUE {$block->block_order}";
                }
            }
        }

        return array_values(array_unique($usages));
    }
}
