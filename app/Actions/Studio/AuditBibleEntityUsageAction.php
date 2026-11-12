<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Services\JgAgente\AgentClientService;

final readonly class AuditBibleEntityUsageAction
{
    /**
     * Audita deterministamente las apariciones de un personaje en escenas y bloques.
     *
     * @return list<string>
     */
    public function auditCharacterUsages(Project $project, Character $char, ?AgentClientService $agentClient = null): array
    {
        $charName = trim($char->name);
        $usages = [];
        $scenes = $project->scenes()->with('scriptBlocks')->orderBy('scene_order')->get();
        $agentClient ??= app(AgentClientService::class);

        foreach ($scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                $texts = [
                    $block->screenplay_content ?? '',
                    $block->structured_text ?? '',
                    $block->raw_text ?? '',
                ];

                $found = false;
                foreach ($texts as $text) {
                    if (! empty($text) && preg_match('/\b'.preg_quote($charName, '/').'\b/iu', (string) $text)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $audit = $agentClient->auditCharacterUsage($charName, $texts);
                    if ($audit['in_use']) {
                        $usages[] = "ESC. {$scene->scene_order} · BLOQUE {$block->block_order}";
                    }
                }
            }
        }

        return array_values(array_unique($usages));
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
