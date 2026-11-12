<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Character;
use App\Models\Project;
use App\Services\JgAgente\AgentClientService;

class BibleSyncService
{
    protected const RESERVED_WORDS = [
        'INT', 'EXT', 'INT/EXT', 'CORTE', 'FADE', 'ESCENA', 'PLANO', 'DÍA', 'DIA', 'NOCHE', 'TARDE', 'MAÑANA',
        'CONTINUA', 'CONTINUO', 'TRANSICIÓN', 'TRANSICION', 'VOZ', 'OFF', 'FLASHBACK', 'FONDO', 'PRIMER',
        'EL', 'LA', 'LOS', 'LAS', 'UN', 'UNA', 'UNOS', 'UNAS', 'DE', 'DEL', 'AL', 'EN', 'POR', 'PARA',
        'CON', 'SIN', 'SOBRE', 'TRAS', 'DURANTE', 'MIENTRAS', 'CUANDO', 'DONDE', 'COMO', 'PERO', 'AUNQUE',
        'LUEGO', 'DESPUES', 'DESPUÉS', 'ENTONCES', 'FINAL', 'INICIO', 'TODO', 'TODOS', 'NADA', 'NADIE',
        'MIRA A SU ALREDEDOR', 'SONIDO EN OFF',
    ];

    public function __construct(
        protected AgentClientService $agentClient
    ) {}

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

        $extracted = $this->requestCharacterExtraction($text, $existingCharacters);
        if (empty($extracted)) {
            $extracted = $this->extractDeterministicCandidates($text, $existingCharacters);
        }

        foreach ($extracted as $char) {
            $this->processCandidateCharacter($char, $result, $existingLower, $project);
        }

        if ($project && $project->exists) {
            $project->load('characters');
        }

        return $result;
    }

    protected function requestCharacterExtraction(string $text, array $existingCharacters): array
    {
        try {
            return $this->agentClient->extractCharacters($text, $existingCharacters);
        } catch (\Throwable) {
            return [];
        }
    }

    protected function processCandidateCharacter(array $char, array &$result, array &$existingLower, ?Project $project): void
    {
        $name = trim((string) ($char['name'] ?? ''));
        $clean = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        $lower = mb_strtolower($clean, 'UTF-8');

        if (mb_strlen($clean) < 3 || in_array($lower, $existingLower, true)) {
            return;
        }

        $bio = (string) ($char['bio'] ?? 'Personaje dramático detectado automáticamente en la acción narrativa.');
        $role = (string) ($char['role'] ?? 'Personaje dramático detectado');

        $result[] = [
            'name' => $clean,
            'role' => $role,
            'personality_notes' => $bio,
            'is_speaking' => $char['is_speaking'] ?? true,
        ];
        $existingLower[] = $lower;

        if ($project && $project->exists) {
            $project->characters()->create([
                'name' => $clean,
                'bio' => $bio,
                'psychology' => null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    public function extractDeterministicCandidates(string $text, array $existingCharacters = []): array
    {
        $existingLower = array_map(fn ($c) => mb_strtolower((string) ($c['name'] ?? ''), 'UTF-8'), $existingCharacters);
        $candidates = $this->gatherDeterministicCandidateNames($text);

        $results = [];
        $seen = [];
        foreach ($candidates as $name) {
            $clean = mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
            $upper = mb_strtoupper($clean, 'UTF-8');
            $lower = mb_strtolower($clean, 'UTF-8');

            if (mb_strlen($clean) >= 3 && ! in_array($upper, self::RESERVED_WORDS, true) && ! in_array($lower, $seen, true) && ! in_array($lower, $existingLower, true)) {
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

    protected function gatherDeterministicCandidateNames(string $text): array
    {
        $candidates = [];
        $rolesRegex = '/\b(?:el|la|un|una)\s+(camarero|camarera|barman|polic[ií]a|agente|detective|inspector|m[eé]dico|doctor|doctora|enfermero|enfermera|cirujano|cirujana|guardia|vigilante|seguridad|portero|conserje|conductor|taxista|ch[oó]fer|cliente|cajero|cajera|dependiente|dependienta|juez|fiscal|abogado|abogada|sacerdote|cura|monja|transe[uú]nte|viandante|chaval)\b/iu';
        if (preg_match_all($rolesRegex, $text, $mRoles)) {
            $candidates = array_merge($candidates, $mRoles[1]);
        }

        if (preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑ\s]{2,25})(?:\s*\([^)]*\))?[ \t]*$/m', $text, $mFountain)) {
            $candidates = array_merge($candidates, $mFountain[1]);
        }

        return $candidates;
    }

    /**
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
}
