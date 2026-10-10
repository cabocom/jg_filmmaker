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
        protected AgentClientService $agentClient,
        protected ?AgentTelemetryService $telemetryService = null
    ) {
        $this->telemetryService = $this->telemetryService ?? app(AgentTelemetryService::class);
    }

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

        $userLocks = $this->resolveUserLocks($block);
        $rawText = $block->raw_text ?? '';

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan)
        $characters = $this->inferCharactersFromText($rawText, $characters);

        $structuredText = $this->agentClient->structureTreatment(
            rawText: $rawText,
            characters: $characters,
            location: $location,
            userLocks: $userLocks
        );

        $telemetry = $this->agentClient->getLastTelemetry();
        if ($telemetry) {
            $userLocks['ai_telemetry']['structured'] = $telemetry;
        }

        if ($project) {
            $this->telemetryService->record(
                projectId: $project->id,
                scriptBlockId: $block->id,
                toolName: 'treatment_structuring',
                inputPayload: ['raw_text' => $rawText, 'characters' => $characters, 'location' => $location],
                outputPayload: $structuredText,
                telemetry: $telemetry
            );
        }

        $block->update([
            'structured_text' => $structuredText,
            'user_locks' => $userLocks,
            'stage' => 'structured',
        ]);

        return $block->refresh();
    }

    /**
     * Transiciona el bloque a la etapa 'screenplay', convirtiéndolo a guion literario.
     */
    public function transitionToScreenplay(ScriptBlock $block): ScriptBlock
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
                'time_of_day' => $block->scene->time_of_day ?? 'DÍA',
            ];
        }

        $sourceText = ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text;
        $userLocks = $this->resolveUserLocks($block);

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan)
        $characters = $this->inferCharactersFromText($sourceText, $characters);

        $screenplayContent = $this->agentClient->formatScreenplay(
            structuredText: $sourceText,
            characters: $characters,
            userLocks: $userLocks,
            location: $location
        );

        $telemetry = $this->agentClient->getLastTelemetry();
        if ($telemetry) {
            $userLocks['ai_telemetry']['screenplay'] = $telemetry;
        }

        if ($project) {
            $this->telemetryService->record(
                projectId: $project->id,
                scriptBlockId: $block->id,
                toolName: 'screenplay_formatter',
                inputPayload: ['structured_text' => $sourceText, 'characters' => $characters, 'location' => $location],
                outputPayload: $screenplayContent,
                telemetry: $telemetry
            );
        }

        $block->update([
            'screenplay_content' => $screenplayContent,
            'user_locks' => $userLocks,
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
        $block->loadMissing('scene.project');
        $project = $block->scene?->project;

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

        $userLocks = $this->resolveUserLocks($block);
        $telemetry = $this->agentClient->getLastTelemetry();
        if ($telemetry) {
            $userLocks['ai_telemetry']['shots'] = $telemetry;
        }

        if ($project) {
            $this->telemetryService->record(
                projectId: $project->id,
                scriptBlockId: $block->id,
                toolName: 'shot_breakdown_detector',
                inputPayload: ['screenplay' => $sourceText],
                outputPayload: $shotsList,
                telemetry: $telemetry
            );
        }

        $block->update([
            'user_locks' => $userLocks,
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

        $userLocks = $this->resolveUserLocks($block);
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

    /**
     * Normaliza user_locks a array asociativo garantizado, previniendo strings o doble serialización.
     *
     * @return array<string, mixed>
     */
    protected function resolveUserLocks(ScriptBlock $block): array
    {
        $locks = $block->user_locks;
        if (is_array($locks)) {
            return $locks;
        }

        if (is_string($locks)) {
            $decoded = json_decode($locks, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                $second = json_decode($decoded, true);
                if (is_array($second)) {
                    return $second;
                }
            }
        }

        return [];
    }

    /**
     * Extrae nombres de personajes adicionales presentes en el texto del autor (ej: Juan).
     *
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    protected function inferCharactersFromText(string $text, array $existingCharacters = []): array
    {
        $existingLower = array_map(fn ($c) => strtolower((string) ($c['name'] ?? '')), $existingCharacters);
        $result = $existingCharacters;

        // Detectar nombres comunes y nombres en mayúsculas
        preg_match_all('/\b(Juan|Pedro|Carlos|Javi|Javier|Ana|María|Laura|Lucía|Elena|David|Pablo|Sofía|Marcos|Antonio|Manuel|Marta|Diego|Luis|Miguel|Andrés|Carmen|Raquel)\b/u', $text, $mCommon);
        preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑ]{2,20})(?:\s*\([^)]*\))?[ \t]*$/m', $text, $mFountain);

        $candidates = array_merge($mCommon[1] ?? [], $mFountain[1] ?? []);
        $reserved = ['INT', 'EXT', 'INT/EXT', 'CORTE', 'FADE', 'ESCENA', 'PLANO', 'DÍA', 'NOCHE', 'TARDE', 'MAÑANA', 'CONTINUA', 'TRANSICIÓN', 'VOZ', 'OFF'];

        foreach ($candidates as $name) {
            $clean = ucfirst(strtolower(trim($name)));
            if (strlen($clean) >= 3 && ! in_array(strtoupper($clean), $reserved) && ! in_array(strtolower($clean), $existingLower)) {
                $result[] = [
                    'name' => $clean,
                    'role' => 'Personaje dramático detectado',
                    'personality_notes' => 'Detectado en la acción narrativa por la IA.',
                ];
                $existingLower[] = strtolower($clean);
            }
        }

        return $result;
    }
}
