<?php

namespace App\Services\JgAgente\Fallbacks;

class CharacterFallbackHandler
{
    /**
     * Stopwords cinematográficas deterministas.
     *
     * @var array<int, string>
     */
    public const CINEMATIC_STOPWORDS = [
        'INT', 'EXT', 'INT/EXT', 'I/E', 'EST', 'ESCENA', 'BLOQUE', 'SECUENCIA',
        'DÍA', 'DIA', 'NOCHE', 'TARDE', 'MAÑANA', 'CONTINUA', 'CONTINUO',
        'CORTE', 'CORTE A', 'CORTE A NEGRO', 'FUNDIDO', 'FUNDIDO A', 'DISUELVE A',
        'FADE', 'FADE IN', 'FADE OUT', 'TRANSICIÓN', 'TRANSICION', 'CRÉDITOS', 'CREDITOS', 'FIN', 'FINAL',
        'PLANO', 'PLANOS', 'PLANO DETALLE', 'PRIMER PLANO', 'PLANO MEDIO', 'PLANO GENERAL',
        'SONIDO EN OFF', 'SONIDO OFF', 'SONIDO', 'VOZ EN OFF', 'VOZ OFF', 'VOZ', 'V.O.', 'OFF', 'CHOF', 'CRASH',
        'MIRA A SU ALREDEDOR', 'MIRANDO A SU ALREDEDOR', 'MIRA ALREDEDOR', 'MIRA A',
        'SE GIRA', 'SE LEVANTA', 'DA UN PASO', 'TRAGA SALIVA', 'SILENCIO', 'SILENCIO ABSOLUTO',
    ];

    public function isCinematicStopword(string $name): bool
    {
        $clean = mb_strtoupper(trim((string) preg_replace('/[^\w\s]/u', '', $name)), 'UTF-8');

        return in_array($clean, self::CINEMATIC_STOPWORDS, true);
    }

    /**
     * Extrae personajes dramáticos reales mediante heurísticas offline.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleCharacterExtraction(array $payload): array
    {
        $text = trim((string) ($payload['text'] ?? ''));
        $existing = (array) ($payload['existing_characters'] ?? []);
        $chars = $this->extractCharactersFromText($text, $existing);

        return [
            'success' => true,
            'output' => 'Extracción determinista completada: '.count($chars).' personaje(s).',
            'metadata' => [
                'characters' => $chars,
                'consensus_summary' => [
                    'total' => count($chars),
                    'strategy' => 'deterministic_fallback',
                ],
            ],
            'characters' => $chars,
        ];
    }

    /**
     * Audita el uso y naturaleza dramática de un personaje.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleCharacterAudit(array $payload): array
    {
        $charName = trim((string) ($payload['character_name'] ?? ''));
        $sceneTexts = (array) ($payload['scene_texts'] ?? []);
        $audit = $this->auditCharacterUsage($charName, $sceneTexts);

        return [
            'success' => true,
            'output' => "Auditoría determinista de '{$charName}': in_use=".($audit['in_use'] ? 'true' : 'false'),
            'metadata' => $audit,
            'in_use' => $audit['in_use'],
        ];
    }

    /**
     * Ejecuta heurística regex de detección de personajes.
     *
     * @param  list<mixed>  $existingCharacters
     * @return list<array<string, mixed>>
     */
    protected function extractCharactersFromText(string $text, array $existingCharacters = []): array
    {
        $existingLower = array_map(fn ($c) => mb_strtolower((string) ($c['name'] ?? ''), 'UTF-8'), $existingCharacters);
        $candidates = [];
        $seen = [];

        $rolesRegex = '/\b(?:el|la|un|una)\s+(camarero|camarera|barman|polic[ií]a|agente|detective|inspector|m[eé]dico|doctor|doctora|enfermero|enfermera|cirujano|cirujana|guardia|vigilante|seguridad|portero|conserje|conductor|taxista|ch[oó]fer|cliente|cajero|cajera|dependiente|dependienta|juez|fiscal|abogado|abogada|sacerdote|cura|monja|transe[uú]nte|viandante|chaval)\b/iu';
        if (preg_match_all($rolesRegex, $text, $mRoles)) {
            foreach ($mRoles[1] as $r) {
                $candidates[] = mb_convert_case(trim($r), MB_CASE_TITLE, 'UTF-8');
            }
        }

        if (preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑa-záéíóúñ\s]{2,25})(?:\s*\([^)]*\))?[ \t]*$/m', $text, $mFountain)) {
            foreach ($mFountain[1] as $f) {
                $candidates[] = mb_convert_case(trim($f), MB_CASE_TITLE, 'UTF-8');
            }
        }

        if (preg_match_all('/^[ \t]*([A-ZÁÉÍÓÚÑa-záéíóúñ\s]{2,25}):/m', $text, $mColons)) {
            foreach ($mColons[1] as $col) {
                $candidates[] = mb_convert_case(trim($col), MB_CASE_TITLE, 'UTF-8');
            }
        }

        $result = [];
        foreach ($candidates as $name) {
            $lower = mb_strtolower($name, 'UTF-8');
            if (mb_strlen($name) < 3 || $this->isCinematicStopword($name) || in_array($lower, $seen, true) || in_array($lower, $existingLower, true)) {
                continue;
            }

            if (! preg_match('/\b'.preg_quote($name, '/').'\b/iu', $text)) {
                continue;
            }

            $seen[] = $lower;
            $result[] = [
                'name' => $name,
                'role' => 'Personaje dramático detectado',
                'bio' => 'Interviene en la escena.',
                'is_speaking' => true,
                'confidence' => 0.85,
                'match_type' => 'deterministic_fallback',
            ];
        }

        return $result;
    }

    /**
     * Audita si un personaje aparece en uso en las escenas.
     *
     * @param  list<string>  $sceneTexts
     * @return array{in_use: bool, is_dramatic_entity: bool, confidence: float, reason: string}
     */
    protected function auditCharacterUsage(string $characterName, array $sceneTexts): array
    {
        if ($this->isCinematicStopword($characterName)) {
            return [
                'in_use' => false,
                'is_dramatic_entity' => false,
                'confidence' => 1.0,
                'reason' => "'{$characterName}' es un término o acotación técnica reservada, no un personaje.",
            ];
        }

        $combined = implode("\n---\n", array_filter($sceneTexts));
        if (! preg_match('/\b'.preg_quote($characterName, '/').'\b/iu', $combined)) {
            return [
                'in_use' => false,
                'is_dramatic_entity' => false,
                'confidence' => 1.0,
                'reason' => "El nombre '{$characterName}' no aparece físicamente en ningún bloque.",
            ];
        }

        $dialoguePattern = '/^[ \t]*'.preg_quote($characterName, '/').'(?:\s*\(.*?\))?:?$/miu';
        if (preg_match($dialoguePattern, $combined)) {
            return [
                'in_use' => true,
                'is_dramatic_entity' => true,
                'confidence' => 0.85,
                'reason' => "'{$characterName}' aparece como interlocutor directo de diálogo en el texto.",
            ];
        }

        $words = preg_split('/\s+/u', trim($characterName));
        $commonParticles = ['a', 'su', 'en', 'de', 'con', 'por', 'el', 'la', 'al', 'del'];
        $hasParticles = false;
        if (count($words ?: []) >= 3) {
            foreach ($words ?: [] as $w) {
                if (in_array(mb_strtolower($w, 'UTF-8'), $commonParticles, true)) {
                    $hasParticles = true;
                    break;
                }
            }
        }

        if ($hasParticles) {
            return [
                'in_use' => false,
                'is_dramatic_entity' => false,
                'confidence' => 0.80,
                'reason' => "'{$characterName}' es una locución o frase descriptiva común en acotación, no un personaje dramático.",
            ];
        }

        return [
            'in_use' => true,
            'is_dramatic_entity' => true,
            'confidence' => 0.70,
            'reason' => "'{$characterName}' aparece mencionado en la acción narrativa.",
        ];
    }
}
