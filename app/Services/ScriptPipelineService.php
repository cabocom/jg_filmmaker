<?php

namespace App\Services;

use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Database\Eloquent\Collection;

class ScriptPipelineService
{
    public function __construct(
        protected AgentClientService $agentClient
    ) {}

    /**
     * Transiciona el bloque a la etapa 'structured', aplicando el contexto del proyecto y candados.
     */
    public function transitionToStructured(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters', 'scene.location');

        $project = $block->scene?->project;
        $characters = $project ? $project->characters->map(fn ($c) => [
            'name' => $c->name,
            'role' => $c->role,
            'personality_notes' => $c->personality_notes,
        ])->toArray() : [];

        $location = null;
        if ($block->scene?->location) {
            $location = [
                'name' => $block->scene->location->name,
                'type' => $block->scene->location->type,
                'lighting_notes' => $block->scene->location->lighting_notes,
            ];
        }

        $userLocks = $block->user_locks ?? [];
        $rawText = $block->raw_text ?? '';

        $structuredText = $this->agentClient->structureTreatment(
            rawText: $rawText,
            characters: $characters,
            location: $location,
            userLocks: $userLocks
        );

        $block->update([
            'structured_text' => $structuredText,
            'stage' => 'structured',
        ]);

        return $block->refresh();
    }

    /**
     * Transiciona el bloque a la etapa 'screenplay', convirtiéndolo a guion literario.
     */
    public function transitionToScreenplay(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters');

        $project = $block->scene?->project;
        $characters = $project ? $project->characters->map(fn ($c) => [
            'name' => $c->name,
            'role' => $c->role,
            'personality_notes' => $c->personality_notes,
        ])->toArray() : [];

        $sourceText = ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text;
        $userLocks = $block->user_locks ?? [];

        $screenplayContent = $this->agentClient->formatScreenplay(
            structuredText: $sourceText,
            characters: $characters,
            userLocks: $userLocks
        );

        $block->update([
            'screenplay_content' => $screenplayContent,
            'stage' => 'screenplay',
        ]);

        return $block->refresh();
    }

    /**
     * Desglosa el guion del bloque en planos técnicos '--- shot N (tipo) ---' y crea entidades Shot.
     *
     * @return Collection<int, Shot>
     */
    public function generateShotsBreakdown(ScriptBlock $block): Collection
    {
        $sourceText = ! empty($block->screenplay_content)
            ? $block->screenplay_content
            : (! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text);

        $breakdownResult = $this->agentClient->breakdownShots($sourceText);
        $shotsList = $breakdownResult['shots'];

        $createdShots = new Collection;

        foreach ($shotsList as $shotData) {
            $shotNumber = (int) ($shotData['shot_number'] ?? 1);
            $shotType = (string) ($shotData['shot_type'] ?? 'PM');
            $description = (string) ($shotData['description'] ?? '');

            $shot = Shot::updateOrCreate(
                [
                    'scene_id' => $block->scene_id,
                    'script_block_id' => $block->id,
                    'shot_number' => $shotNumber,
                ],
                [
                    'shot_type' => $shotType,
                    'description' => $description,
                ]
            );

            // Asegurar al menos una toma base 1 si es un plano nuevo
            if ($shot->wasRecentlyCreated && $shot->takes()->count() === 0) {
                Take::create([
                    'shot_id' => $shot->id,
                    'take_number' => 1,
                    'is_good_take' => false,
                    'editorial_notes' => 'Toma inicial generada por pipeline de desglose.',
                ]);
            }

            $createdShots->push($shot);
        }

        $block->update([
            'stage' => 'shots',
        ]);

        return $createdShots;
    }

    /**
     * Registra un cambio manual del usuario en el esquema user_locks del bloque.
     */
    public function recordManualOverride(ScriptBlock $block, string $stage, string $originalText, string $overrideText): ScriptBlock
    {
        if ($originalText === $overrideText) {
            return $block;
        }

        $userLocks = $block->user_locks ?? [];
        $manualDiffs = $userLocks['manual_diffs'] ?? [];

        $manualDiffs[] = [
            'stage' => $stage,
            'original_ai' => $originalText,
            'user_override' => $overrideText,
            'applied_at' => now()->toIso8601String(),
        ];

        $lockedFields = $userLocks['locked_fields'] ?? [];
        if (! in_array($stage, $lockedFields, true)) {
            $lockedFields[] = $stage;
        }

        $userLocks['manual_diffs'] = $manualDiffs;
        $userLocks['locked_fields'] = $lockedFields;

        $block->update([
            'user_locks' => $userLocks,
        ]);

        return $block;
    }
}
