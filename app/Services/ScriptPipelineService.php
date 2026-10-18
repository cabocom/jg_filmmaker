<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Project;
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

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan, Camarero)
        $characters = $this->inferCharactersFromText($rawText, $characters, $project);

        $structuredText = $this->agentClient->structureTreatment(
            rawText: $rawText,
            characters: $characters,
            location: $location,
            userLocks: $userLocks
        );

        $structuredText = $this->capitalizeCharacterNames($structuredText, $characters, 'structured');

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

        // Purgar diffs y bloqueos manuales obsoletos de 'structured' ya que ha sido regenerado por la IA desde el bruto
        if (! empty($userLocks['manual_diffs'])) {
            $userLocks['manual_diffs'] = array_values(array_filter(
                $userLocks['manual_diffs'],
                fn ($diff) => ($diff['stage'] ?? null) !== 'structured'
            ));
        }
        if (! empty($userLocks['locked_fields'])) {
            $userLocks['locked_fields'] = array_values(array_filter(
                $userLocks['locked_fields'],
                fn ($field) => $field !== 'structured'
            ));
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

        // Enriquecer contexto de personajes con nombres inferidos del texto (ej: Juan, Camarero)
        $characters = $this->inferCharactersFromText($sourceText, $characters, $project);

        $screenplayContent = $this->agentClient->formatScreenplay(
            structuredText: $sourceText,
            characters: $characters,
            userLocks: $userLocks,
            location: $location
        );

        $screenplayContent = $this->capitalizeCharacterNames($screenplayContent, $characters, 'screenplay');

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

        // Purgar diffs y bloqueos manuales obsoletos de 'screenplay' ya que ha sido regenerado por la IA
        if (! empty($userLocks['manual_diffs'])) {
            $userLocks['manual_diffs'] = array_values(array_filter(
                $userLocks['manual_diffs'],
                fn ($diff) => ($diff['stage'] ?? null) !== 'screenplay'
            ));
        }
        if (! empty($userLocks['locked_fields'])) {
            $userLocks['locked_fields'] = array_values(array_filter(
                $userLocks['locked_fields'],
                fn ($field) => $field !== 'screenplay'
            ));
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

        // Si se bloquea el guión, también hay que bloquear el tratamiento para que no se pueda regenerar desde el bruto
        if ($stage === 'screenplay' && ! in_array('structured', $lockedFields, true)) {
            $lockedFields[] = 'structured';
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
        $result = $existingCharacters;

        $candidates = [];

        // 1. Roles dramáticos y oficios con artículo (ej: "el camarero", "la camarera", "el policía", "el médico", "el guardia")
        $rolesRegex = '/\b(?:el|la|un|una)\s+(camarero|camarera|barman|polic[ií]a|agente|detective|inspector|m[eé]dico|doctor|doctora|enfermero|enfermera|cirujano|cirujana|guardia|vigilante|seguridad|portero|conserje|conductor|taxista|ch[oó]fer|cliente|cajero|cajera|dependiente|dependienta|juez|fiscal|abogado|abogada|sacerdote|cura|monja|mendigo|sicario|asesino|ladr[oó]n)\b/iu';
        if (preg_match_all($rolesRegex, $text, $mRoles)) {
            $candidates = array_merge($candidates, $mRoles[1]);
        }

        // Roles aislados que ejecutan acciones explícitas (ej: "camarero le increpa", "camarero intenta salir")
        $standaloneRolesRegex = '/\b(camarero|camarera|barman|polic[ií]a|detective|m[eé]dico|doctor|doctora|enfermero|enfermera|guardia|portero|taxista|ch[oó]fer)\b/iu';
        if (preg_match_all($standaloneRolesRegex, $text, $mStandalone)) {
            $candidates = array_merge($candidates, $mStandalone[1]);
        }

        // 2. Nombres entre comillas con inicial mayúscula (ej: "Estrella Casilda")
        if (preg_match_all('/["«]([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*)["»]/u', $text, $mQuoted)) {
            $candidates = array_merge($candidates, $mQuoted[1]);
        }

        // 3. Nombres propios comunes y compuestos
        preg_match_all('/\b(Juan|Pedro|Carlos|Javi|Javier|Ana|María|Laura|Lucía|Elena|David|Pablo|Sofía|Marcos|Antonio|Manuel|Marta|Diego|Luis|Miguel|Andrés|Carmen|Raquel|Estrella\s+Casilda)\b/u', $text, $mCommon);
        $candidates = array_merge($candidates, $mCommon[1] ?? []);

        // 4. Encabezados de diálogo estilo Fountain (ej: CAMARERO, ESTRELLA CASILDA)
        if (preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑ\s]{2,25})(?:\s*\([^)]*\))?[ \t]*$/m', $text, $mFountain)) {
            $candidates = array_merge($candidates, $mFountain[1]);
        }

        $reserved = [
            'INT', 'EXT', 'INT/EXT', 'CORTE', 'FADE', 'ESCENA', 'PLANO', 'DÍA', 'DIA', 'NOCHE', 'TARDE', 'MAÑANA',
            'CONTINUA', 'CONTINUO', 'TRANSICIÓN', 'TRANSICION', 'VOZ', 'OFF', 'FLASHBACK', 'FONDO', 'PRIMER',
            'EL', 'LA', 'LOS', 'LAS', 'UN', 'UNA', 'UNOS', 'UNAS', 'DE', 'DEL', 'AL', 'EN', 'POR', 'PARA',
            'CON', 'SIN', 'SOBRE', 'TRAS', 'DURANTE', 'MIENTRAS', 'CUANDO', 'DONDE', 'COMO', 'PERO', 'AUNQUE',
            'LUEGO', 'DESPUES', 'DESPUÉS', 'ENTONCES', 'FINAL', 'INICIO', 'TODO', 'TODOS', 'NADA', 'NADIE',
        ];

        foreach ($candidates as $name) {
            $clean = mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
            $upper = mb_strtoupper($clean, 'UTF-8');
            $lower = mb_strtolower($clean, 'UTF-8');

            if (mb_strlen($clean) >= 3 && ! in_array($upper, $reserved, true) && ! in_array($lower, $existingLower, true)) {
                $result[] = [
                    'name' => $clean,
                    'role' => 'Personaje dramático detectado',
                    'personality_notes' => 'Detectado automáticamente en la acción narrativa por la IA.',
                ];
                $existingLower[] = $lower;

                // Sincronizar y registrar en la base de datos del proyecto si procede
                if ($project && $project->exists) {
                    $existsInDb = $project->characters()->whereRaw('LOWER(name) = ?', [$lower])->exists();
                    if (! $existsInDb) {
                        $project->characters()->create([
                            'name' => $clean,
                            'bio' => 'Personaje dramático detectado automáticamente en la acción narrativa.',
                            'psychology' => 'Interviene activamente en el desarrollo de la escena.',
                        ]);
                    }
                }
            }
        }

        if ($project && $project->exists) {
            $project->load('characters');
        }

        return $result;
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

            // Si la escena ya tiene una localización asociada
            if ($scene->location_id) {
                $currentLoc = $project->locations()->find($scene->location_id);
                if ($currentLoc) {
                    $currentName = mb_strtoupper($currentLoc->name, 'UTF-8');
                    // Si el nombre en el slugline cambió respecto a la localización asociada
                    if ($currentName !== $parsedName) {
                        // Comprobar si ya existe una localización con el nuevo nombre en el proyecto
                        $targetLoc = $project->locations()
                            ->whereRaw('UPPER(name) = ?', [$parsedName])
                            ->first();

                        if ($targetLoc) {
                            // Asociar la escena a la localización con el nuevo nombre
                            $scene->update(['location_id' => $targetLoc->id]);

                            // Si la localización antigua no la usa ninguna otra escena, la eliminamos
                            $otherUsages = $project->scenes()
                                ->where('id', '!=', $scene->id)
                                ->where('location_id', $currentLoc->id)
                                ->count();
                            if ($otherUsages === 0) {
                                $currentLoc->delete();
                            }
                        } else {
                            // Si ninguna otra escena usa la localización antigua, la renombramos directamente
                            $otherUsages = $project->scenes()
                                ->where('id', '!=', $scene->id)
                                ->where('location_id', $currentLoc->id)
                                ->count();
                            if ($otherUsages === 0) {
                                $currentLoc->update([
                                    'name' => $parsedName,
                                    'type' => $parsedType,
                                ]);
                            } else {
                                // Otras escenas siguen usando la antigua, creamos la nueva localización para esta escena
                                $newLoc = $project->locations()->create([
                                    'name' => $parsedName,
                                    'type' => $parsedType,
                                    'art_department_notes' => 'Localización detectada automáticamente en el guión.',
                                ]);
                                $scene->update(['location_id' => $newLoc->id]);
                            }
                        }
                    } else {
                        // El nombre coincide, asegurar tipo consistente si difiere
                        if (empty($currentLoc->type) || $currentLoc->type !== $parsedType) {
                            $currentLoc->update(['type' => $parsedType]);
                        }
                    }
                } else {
                    // El location_id apuntaba a una localización inexistente, resetear y buscar/crear
                    $scene->update(['location_id' => null]);
                }
            }

            // Si la escena NO tiene localización asociada (o se reseteó arriba)
            if (! $scene->location_id) {
                // Buscar si ya existe una localización con este nombre
                $loc = $project->locations()
                    ->whereRaw('UPPER(name) = ?', [$parsedName])
                    ->first();

                if (! $loc) {
                    $loc = $project->locations()->create([
                        'name' => $parsedName,
                        'type' => $parsedType,
                        'art_department_notes' => 'Localización detectada automáticamente en el guión.',
                    ]);
                }

                $scene->update(['location_id' => $loc->id]);
            }
        }

        $project->load('locations');
    }
}
