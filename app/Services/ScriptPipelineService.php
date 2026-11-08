<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScriptPipelineService
{
    public function __construct(
        protected AgentClientService $agentClient,
        protected ?AgentTelemetryService $telemetryService = null
    ) {
        $this->telemetryService = $this->telemetryService ?? app(AgentTelemetryService::class);
    }

    /**
     * Valida la disponibilidad de cuota diaria de tokens de IA para el usuario del proyecto.
     *
     * @throws QuotaExceededException
     */
    public function assertUserQuotaAvailable(?Project $project, int $estimatedTokens = 100): void
    {
        if (! $project) {
            return;
        }

        $user = $project->user ?? (auth()->check() ? auth()->user() : null);
        if (! $user) {
            return;
        }

        if (! $user->hasDailyTokensAvailable($estimatedTokens)) {
            $limit = $user->getDailyTokenLimit();
            $available = $user->getTokensAvailableToday();

            throw new QuotaExceededException(
                message: 'Has alcanzado tu cupo diario de tokens de IA ('.number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.',
                requiredTokens: $estimatedTokens,
                availableTokens: $available
            );
        }
    }

    /**
     * Transiciona el bloque a la etapa 'structured', aplicando el contexto del proyecto y candados.
     */
    public function transitionToStructured(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters', 'scene.project.scenes.scriptBlocks', 'scene.location');

        $project = $block->scene?->project;
        if ($project) {
            $this->syncProjectCharacters($project);
            $this->syncProjectLocations($project);
        }

        $characters = $project ? $project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray() : [];

        $location = null;
        if ($block->scene?->location) {
            $location = $block->scene->location->toAgentContext();
            if ($block->scene?->time_of_day) {
                $location['time_of_day'] = $block->scene->time_of_day;
            }
        }

        if ($block->isStageLocked('structured')) {
            return $block;
        }

        $userLocks = $this->resolveUserLocks($block);
        $rawText = $block->raw_text ?? '';

        $estimatedTokens = max(50, (int) ceil(mb_strlen((string) $rawText) / 4) + 150);
        $this->assertUserQuotaAvailable($project, $estimatedTokens);

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan, Camarero)
        $characters = $this->inferCharactersFromText($rawText, $characters, $project);

        $structuredText = $this->agentClient->structureTreatment(
            rawText: $rawText,
            characters: $characters,
            location: $location,
            userLocks: $this->sanitizeUserLocksForAgent($userLocks)
        );

        $structuredText = $this->capitalizeCharacterNames($structuredText, $characters, 'structured');

        $telemetry = $this->agentClient->getLastTelemetry();

        $updatedBlock = DB::transaction(function () use ($block, $structuredText) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
            if (! $fresh) {
                return $block;
            }

            // Si mientras se ejecutaba la inferencia de IA el usuario bloqueó la etapa, respetamos su candado
            if ($fresh->isStageLocked('structured')) {
                return $fresh;
            }

            $freshLocks = $this->resolveUserLocks($fresh);
            unset($freshLocks['ai_telemetry']);

            // Archivar diffs manuales de 'structured' en vez de destruirlos
            if (! empty($freshLocks['manual_diffs'])) {
                $archived = (array) ($freshLocks['archived_diffs'] ?? []);
                $activeDiffs = [];
                foreach ($freshLocks['manual_diffs'] as $diff) {
                    $diffStage = $diff['stage'] ?? $diff['target'] ?? null;
                    if ($diffStage === 'structured') {
                        $diff['archived_at'] = now()->toIso8601String();
                        $archived[] = $diff;
                    } else {
                        $activeDiffs[] = $diff;
                    }
                }
                $freshLocks['archived_diffs'] = $archived;
                $freshLocks['manual_diffs'] = $activeDiffs;
            }
            if (! empty($freshLocks['locked_fields'])) {
                $freshLocks['locked_fields'] = array_values(array_filter(
                    $freshLocks['locked_fields'],
                    fn ($field) => $field !== 'structured'
                ));
            }

            $fresh->update([
                'structured_text' => $structuredText,
                'user_locks' => $freshLocks,
                'stage' => 'structured',
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);

            return $fresh;
        });

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

        return $updatedBlock->refresh();
    }

    /**
     * Transiciona el bloque a la etapa 'screenplay', convirtiéndolo a guion literario.
     */
    public function transitionToScreenplay(ScriptBlock $block): ScriptBlock
    {
        $block->loadMissing('scene.project.characters', 'scene.project.scenes.scriptBlocks', 'scene.location');

        $project = $block->scene?->project;
        if ($project) {
            $this->syncProjectCharacters($project);
            $this->syncProjectLocations($project);
        }

        $characters = $project ? $project->characters()->get()->map(fn (Character $c) => $c->toAgentContext())->toArray() : [];

        $location = null;
        if ($block->scene?->location) {
            $location = $block->scene->location->toAgentContext();
            $location['time_of_day'] = $block->scene->time_of_day ?? 'DÍA';
        }

        if ($block->isStageLocked('screenplay')) {
            return $block;
        }

        $sourceText = ! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text;
        $userLocks = $this->resolveUserLocks($block);

        $estimatedTokens = max(50, (int) ceil(mb_strlen((string) $sourceText) / 4) + 200);
        $this->assertUserQuotaAvailable($project, $estimatedTokens);

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan, Camarero)
        $characters = $this->inferCharactersFromText($sourceText, $characters, $project);

        $screenplayContent = $this->agentClient->formatScreenplay(
            structuredText: $sourceText,
            characters: $characters,
            userLocks: $this->sanitizeUserLocksForAgent($userLocks),
            location: $location
        );

        $screenplayContent = $this->capitalizeCharacterNames($screenplayContent, $characters, 'screenplay');

        $telemetry = $this->agentClient->getLastTelemetry();

        $updatedBlock = DB::transaction(function () use ($block, $screenplayContent) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
            if (! $fresh) {
                return $block;
            }

            // Si mientras se ejecutaba la inferencia de IA el usuario bloqueó la etapa, respetamos su candado
            if ($fresh->isStageLocked('screenplay')) {
                return $fresh;
            }

            $freshLocks = $this->resolveUserLocks($fresh);
            unset($freshLocks['ai_telemetry']);

            // Archivar diffs manuales de 'screenplay' en vez de destruirlos
            if (! empty($freshLocks['manual_diffs'])) {
                $archived = (array) ($freshLocks['archived_diffs'] ?? []);
                $activeDiffs = [];
                foreach ($freshLocks['manual_diffs'] as $diff) {
                    $diffStage = $diff['stage'] ?? $diff['target'] ?? null;
                    if ($diffStage === 'screenplay') {
                        $diff['archived_at'] = now()->toIso8601String();
                        $archived[] = $diff;
                    } else {
                        $activeDiffs[] = $diff;
                    }
                }
                $freshLocks['archived_diffs'] = $archived;
                $freshLocks['manual_diffs'] = $activeDiffs;
            }
            if (! empty($freshLocks['locked_fields'])) {
                $freshLocks['locked_fields'] = array_values(array_filter(
                    $freshLocks['locked_fields'],
                    fn ($field) => $field !== 'screenplay'
                ));
            }

            $fresh->update([
                'screenplay_content' => $screenplayContent,
                'user_locks' => $freshLocks,
                'stage' => 'screenplay',
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);

            return $fresh;
        });

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

        return $updatedBlock->refresh();
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

        if ($block->isStageLocked('shots')) {
            return $block->shots()->orderBy('shot_number')->get();
        }

        $sourceText = ! empty($block->screenplay_content)
            ? $block->screenplay_content
            : (! empty($block->structured_text) ? $block->structured_text : (string) $block->raw_text);

        $estimatedTokens = max(50, (int) ceil(mb_strlen((string) $sourceText) / 4) + 150);
        $this->assertUserQuotaAvailable($project, $estimatedTokens);

        $breakdownResult = $this->agentClient->breakdownShots($sourceText);
        $shotsList = $breakdownResult['shots'];

        $createdShots = new Collection;
        $usedShotIds = [];

        foreach ($shotsList as $shotData) {
            $shotNumber = (int) ($shotData['shot_number'] ?? 1);
            $shotType = (string) ($shotData['shot_type'] ?? 'PM');
            $description = (string) ($shotData['description'] ?? '');

            // Si el plano ya existe y tiene tomas grabadas reales (vídeo, buena toma o notas de rodaje), preservarlo
            $existingShot = Shot::where('scene_id', $block->scene_id)
                ->where('script_block_id', $block->id)
                ->where('shot_number', $shotNumber)
                ->first();

            $hasRecordedTakes = $existingShot && $existingShot->takes()
                ->where(function ($q) {
                    $q->whereNotNull('file_path')
                        ->orWhere('is_good_take', true)
                        ->orWhere('editorial_notes', '!=', 'Toma inicial generada por pipeline de desglose.');
                })
                ->exists();

            if ($hasRecordedTakes) {
                $shot = $existingShot;
            } else {
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
            }

            // Asegurar al menos una toma base 1 si es un plano nuevo
            if ($shot->wasRecentlyCreated && $shot->takes()->count() === 0) {
                Take::create([
                    'shot_id' => $shot->id,
                    'take_number' => 1,
                    'is_good_take' => false,
                    'editorial_notes' => 'Toma inicial generada por pipeline de desglose.',
                ]);
            }

            $usedShotIds[] = $shot->id;
            $createdShots->push($shot);
        }

        // Eliminar planos antiguos de este bloque que ya no forman parte del desglose y no tienen tomas reales
        $block->shots()->whereNotIn('id', $usedShotIds)->each(function (Shot $obsoleteShot) {
            $hasRecorded = $obsoleteShot->takes()
                ->where(function ($q) {
                    $q->whereNotNull('file_path')
                        ->orWhere('is_good_take', true)
                        ->orWhere('editorial_notes', '!=', 'Toma inicial generada por pipeline de desglose.');
                })
                ->exists();

            if (! $hasRecorded) {
                $obsoleteShot->takes()->delete();
                $obsoleteShot->delete();
            }
        });

        $telemetry = $this->agentClient->getLastTelemetry();

        DB::transaction(function () use ($block) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
            if (! $fresh) {
                return;
            }

            $freshLocks = $this->resolveUserLocks($fresh);
            unset($freshLocks['ai_telemetry']);

            $fresh->update([
                'user_locks' => $freshLocks,
                'stage' => 'shots',
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);
        });

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

        return $createdShots;
    }

    /**
     * Registra un cambio manual del usuario en el esquema user_locks del bloque de forma atómica.
     */
    public function recordManualOverride(ScriptBlock $block, string $stage, string $originalText, string $overrideText): ScriptBlock
    {
        if ($originalText === $overrideText) {
            return $block;
        }

        return $block->mutateUserLocks(function (array $userLocks) use ($stage, $originalText, $overrideText) {
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

            // Si se bloquea el guión, también hay que bloquear el tratamiento para que no se pueda regenerar desde el bruto
            if ($stage === 'screenplay' && ! in_array('structured', $lockedFields, true)) {
                $lockedFields[] = 'structured';
            }

            $userLocks['manual_diffs'] = $manualDiffs;
            $userLocks['locked_fields'] = $lockedFields;

            return $userLocks;
        });
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
     * Sincroniza y detecta personajes a lo largo de todas las escenas del proyecto y los guarda en la Biblia.
     */
    public function syncProjectCharacters(Project $project): void
    {
        $project->loadMissing('scenes.scriptBlocks', 'characters');

        $fullText = '';
        foreach ($project->scenes as $scene) {
            foreach ($scene->scriptBlocks as $b) {
                $fullText .= ' '.($b->raw_text ?? '').' '.($b->structured_text ?? '').' '.($b->screenplay_content ?? '');
            }
        }

        $existing = $project->characters->map(fn (Character $c) => $c->toAgentContext())->toArray();

        $this->inferCharactersFromText($fullText, $existing, $project);
    }

    /**
     * Extrae nombres de personajes y roles dramáticos adicionales presentes en el texto (ej: Juan, Estrella Casilda, Camarero).
     *
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    public function inferCharactersFromText(string $text, array $existingCharacters = [], ?Project $project = null): array
    {
        $existingLower = array_map(fn ($c) => mb_strtolower((string) ($c['name'] ?? ''), 'UTF-8'), $existingCharacters);
        if ($project && $project->exists) {
            $dbNames = $project->characters()->pluck('name')->map(fn ($n) => mb_strtolower((string) $n, 'UTF-8'))->all();
            $existingLower = array_unique(array_merge($existingLower, $dbNames));
        }
        $result = $existingCharacters;

        // Inferencia cognitiva híbrida conectando con la tool canónica extract_characters en jg_plugin_filmmaker
        $extracted = [];
        try {
            $extracted = $this->agentClient->extractCharacters($text, $existingCharacters);
        } catch (\Throwable) {
            $extracted = [];
        }

        // Si la llamada al agente no devolvió candidatos o falló, recurrir al motor determinista de respaldo
        if (empty($extracted)) {
            $extracted = $this->extractDeterministicCandidates($text, $existingCharacters);
        }

        foreach ($extracted as $char) {
            $name = trim((string) ($char['name'] ?? ''));
            $clean = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            $lower = mb_strtolower($clean, 'UTF-8');

            if (mb_strlen($clean) >= 3 && ! in_array($lower, $existingLower, true)) {
                $bio = (string) ($char['bio'] ?? 'Personaje dramático detectado automáticamente en la acción narrativa.');
                $role = (string) ($char['role'] ?? 'Personaje dramático detectado');

                $result[] = [
                    'name' => $clean,
                    'role' => $role,
                    'personality_notes' => $bio,
                    'is_speaking' => $char['is_speaking'] ?? true,
                ];
                $existingLower[] = $lower;

                // Sincronizar y registrar en la base de datos del proyecto si procede (evitando N+1 queries)
                if ($project && $project->exists) {
                    $project->characters()->create([
                        'name' => $clean,
                        'bio' => $bio,
                        'psychology' => 'Interviene activamente en el desarrollo de la escena.',
                    ]);
                }
            }
        }

        if ($project && $project->exists) {
            $project->load('characters');
        }

        return $result;
    }

    /**
     * Motor determinista de respaldo para extracción de personajes cuando el agente no está disponible.
     *
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    public function extractDeterministicCandidates(string $text, array $existingCharacters = []): array
    {
        $existingLower = array_map(fn ($c) => mb_strtolower((string) ($c['name'] ?? ''), 'UTF-8'), $existingCharacters);
        $candidates = [];

        // 1. Roles con artículo
        $rolesRegex = '/\b(?:el|la|un|una)\s+(camarero|camarera|barman|polic[ií]a|agente|detective|inspector|m[eé]dico|doctor|doctora|enfermero|enfermera|cirujano|cirujana|guardia|vigilante|seguridad|portero|conserje|conductor|taxista|ch[oó]fer|cliente|cajero|cajera|dependiente|dependienta|juez|fiscal|abogado|abogada|sacerdote|cura|monja|transe[uú]nte|viandante|chaval)\b/iu';
        if (preg_match_all($rolesRegex, $text, $mRoles)) {
            $candidates = array_merge($candidates, $mRoles[1]);
        }

        // 2. Encabezados de diálogo estilo Fountain
        if (preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑ\s]{2,25})(?:\s*\([^)]*\))?[ \t]*$/m', $text, $mFountain)) {
            $candidates = array_merge($candidates, $mFountain[1]);
        }

        $reserved = [
            'INT', 'EXT', 'INT/EXT', 'CORTE', 'FADE', 'ESCENA', 'PLANO', 'DÍA', 'DIA', 'NOCHE', 'TARDE', 'MAÑANA',
            'CONTINUA', 'CONTINUO', 'TRANSICIÓN', 'TRANSICION', 'VOZ', 'OFF', 'FLASHBACK', 'FONDO', 'PRIMER',
            'EL', 'LA', 'LOS', 'LAS', 'UN', 'UNA', 'UNOS', 'UNAS', 'DE', 'DEL', 'AL', 'EN', 'POR', 'PARA',
            'CON', 'SIN', 'SOBRE', 'TRAS', 'DURANTE', 'MIENTRAS', 'CUANDO', 'DONDE', 'COMO', 'PERO', 'AUNQUE',
            'LUEGO', 'DESPUES', 'DESPUÉS', 'ENTONCES', 'FINAL', 'INICIO', 'TODO', 'TODOS', 'NADA', 'NADIE',
            'MIRA A SU ALREDEDOR', 'SONIDO EN OFF',
        ];

        $results = [];
        $seen = [];
        foreach ($candidates as $name) {
            $clean = mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
            $upper = mb_strtoupper($clean, 'UTF-8');
            $lower = mb_strtolower($clean, 'UTF-8');

            if (mb_strlen($clean) >= 3 && ! in_array($upper, $reserved, true) && ! in_array($lower, $seen, true) && ! in_array($lower, $existingLower, true)) {
                $seen[] = $lower;
                $results[] = [
                    'name' => $clean,
                    'role' => 'Personaje dramático detectado',
                    'bio' => 'Interviene en la escena.',
                    'is_speaking' => true,
                ];
            }
        }

        return $results;
    }

    /**
     * Asegura que los nombres de los personajes aparezcan COMPLETAMENTE EN MAYÚSCULAS
     * en todas las etapas generadas por la IA.
     *
     * @param  array<int, array<string, mixed>>  $characters
     */
    public function capitalizeCharacterNames(string $text, array $characters, string $stage = 'structured'): string
    {
        if (empty($text) || empty($characters)) {
            return $text;
        }

        foreach ($characters as $char) {
            $name = is_array($char) ? ($char['name'] ?? '') : (string) $char;
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $canonicalUpper = mb_strtoupper($name, 'UTF-8');
            $pattern = '/\b'.preg_quote($name, '/').'\b/iu';
            $text = preg_replace($pattern, $canonicalUpper, $text);
        }

        return $text;
    }

    /**
     * Parsea un slugline (encabezado canónico) extrayendo tipo (INT/EXT), nombre de localización y momento del día.
     *
     * @return array{type: string, name: string, time_of_day: string}
     */
    public function parseSlugline(string $slugline): array
    {
        $slugline = trim($slugline);
        if (empty($slugline)) {
            return [
                'type' => 'INT',
                'name' => 'LOCALIZACIÓN',
                'time_of_day' => 'DÍA',
            ];
        }

        $type = 'INT';
        $name = '';
        $timeOfDay = 'DÍA';

        // Detectar si empieza por INT/EXT, INT./EXT., I/E., INT., EXT., etc.
        if (preg_match('/^(INT\/EXT|INT\.\/EXT\.|INT|EXT|I\/E)\.?\s*(.*?)(?:\s*[-–—]\s*([^\d\n\r]+?))?(?:\s+\d+)?$/iu', $slugline, $matches)) {
            $rawType = strtoupper(trim($matches[1]));
            if (str_contains($rawType, 'EXT') && str_contains($rawType, 'INT')) {
                $type = 'INT/EXT';
            } elseif (str_contains($rawType, 'EXT')) {
                $type = 'EXT';
            } else {
                $type = 'INT';
            }

            $name = trim($matches[2] ?? '');
            // Limpiar posibles guiones o puntos al inicio o final del nombre
            $name = trim(preg_replace('/^[-–—\.\s]+|[-–—\.\s]+$/u', '', $name));

            if (! empty($matches[3])) {
                $timeOfDay = strtoupper(trim($matches[3]));
            }
        } else {
            // Si no tiene el formato estándar INT./EXT., separar por guión si existe
            $parts = preg_split('/[-–—]/u', $slugline, 2);
            $headerPart = trim($parts[0] ?? '');
            $timePart = trim($parts[1] ?? '');

            if (preg_match('/^(INT\/EXT|INT|EXT)/i', $headerPart, $tm)) {
                $rawType = strtoupper($tm[1]);
                $type = str_contains($rawType, 'EXT') && str_contains($rawType, 'INT') ? 'INT/EXT' : $rawType;
                $name = trim(preg_replace('/^(INT\/EXT|INT|EXT)[\.\s]*/i', '', $headerPart));
            } else {
                $name = $headerPart;
            }

            if (! empty($timePart)) {
                $timeOfDay = strtoupper(trim(preg_replace('/\s+\d+$/', '', $timePart)));
            }
        }

        $name = mb_strtoupper($name, 'UTF-8');
        if (empty($name)) {
            $name = 'LOCALIZACIÓN';
        }

        return [
            'type' => $type,
            'name' => $name,
            'time_of_day' => $timeOfDay ?: 'DÍA',
        ];
    }

    /**
     * Sincroniza las localizaciones deducidas de los sluglines de las escenas con la tabla 'locations' de la Biblia del proyecto.
     * Asocia automáticamente el location_id correspondiente a cada escena.
     */
    public function syncProjectLocations(Project $project): void
    {
        if (! $project->exists) {
            return;
        }

        $scenes = $project->scenes()->orderBy('scene_order')->get();
        if ($scenes->isEmpty()) {
            return;
        }

        // Precargar en memoria todas las localizaciones existentes del proyecto (O(1) lookups)
        /** @var Collection<int, Location> $locations */
        $locations = $project->locations()->get();
        $locMap = [];
        foreach ($locations as $loc) {
            $locMap[mb_strtoupper($loc->name, 'UTF-8')] = $loc;
        }

        foreach ($scenes as $scene) {
            if (empty($scene->slugline)) {
                continue;
            }

            $parsed = $this->parseSlugline($scene->slugline);
            $parsedName = $parsed['name'];
            $parsedType = $parsed['type'];
            $parsedTime = $parsed['time_of_day'];

            // Actualizar time_of_day de la escena si estaba vacío
            if (empty($scene->time_of_day) && ! empty($parsedTime)) {
                $scene->update(['time_of_day' => $parsedTime]);
            }

            $targetLoc = $locMap[$parsedName] ?? null;
            if (! $targetLoc) {
                $targetLoc = $project->locations()->create([
                    'name' => $parsedName,
                    'type' => $parsedType,
                    'art_department_notes' => 'Localización detectada automáticamente en el guión.',
                ]);
                $locMap[$parsedName] = $targetLoc;
            }

            // Asociar a la escena sólo si difiere
            if ($scene->location_id !== $targetLoc->id) {
                $scene->update(['location_id' => $targetLoc->id]);
            }
        }

        $project->load('locations');
    }

    /**
     * Sanitiza el esquema de candados para el agente, enviando exclusivamente términos de gobernanza y autoría.
     * Previene fugas de telemetría histórica o sobrecostes de tokens.
     *
     * @param  array<string, mixed>  $userLocks
     * @return array{pinned_terms: array<string, string>, manual_diffs: array<int, mixed>, locked_fields: list<string>}
     */
    public function sanitizeUserLocksForAgent(array $userLocks): array
    {
        return [
            'pinned_terms' => (array) ($userLocks['pinned_terms'] ?? []),
            'manual_diffs' => (array) ($userLocks['manual_diffs'] ?? []),
            'locked_fields' => (array) ($userLocks['locked_fields'] ?? []),
        ];
    }

    /**
     * Procesa en lote una colección de bloques hacia la etapa 'structured'.
     *
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchStructureBlocks(iterable $blocks): array
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
                $this->transitionToStructured($block);
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
     * Procesa en lote una colección de bloques hacia la etapa 'screenplay'.
     *
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchFormatBlocksToScreenplay(iterable $blocks): array
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
                $this->transitionToScreenplay($block);
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
     * Procesa en lote una colección de bloques hacia el desglose de planos ('shots').
     *
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{processed: int, skipped: int, errors: list<string>}
     */
    public function batchGenerateShots(iterable $blocks): array
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
                $this->generateShotsBreakdown($block);
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
     * Ejecuta el pipeline completo de forma secuencial en lote (Bruto -> Tratamiento -> Guion -> Planos).
     *
     * @param  iterable<ScriptBlock>  $blocks
     * @return array{structured: int, screenplay: int, shots: int, errors: list<string>}
     */
    public function batchRunFullPipeline(iterable $blocks): array
    {
        $resStructured = $this->batchStructureBlocks($blocks);
        $freshBlocks = collect($blocks)->map(fn ($b) => $b->fresh())->filter();
        $resScreenplay = $this->batchFormatBlocksToScreenplay($freshBlocks);
        $freshBlocks = $freshBlocks->map(fn ($b) => $b->fresh())->filter();
        $resShots = $this->batchGenerateShots($freshBlocks);

        return [
            'structured' => $resStructured['processed'],
            'screenplay' => $resScreenplay['processed'],
            'shots' => $resShots['processed'],
            'errors' => array_merge($resStructured['errors'], $resScreenplay['errors'], $resShots['errors']),
        ];
    }
}
