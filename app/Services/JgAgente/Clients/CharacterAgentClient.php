<?php

namespace App\Services\JgAgente\Clients;

use App\Services\JgAgente\AgentHttpTransport;
use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;
use App\Services\JgAgente\HmacSigner;
use App\Services\JgAgente\TelemetryRecorder;

class CharacterAgentClient
{
    public function __construct(
        protected AgentHttpTransport $transport,
        protected HmacSigner $signer,
        protected DeterministicFallbackEngine $fallbackEngine,
        protected TelemetryRecorder $telemetry
    ) {}

    /**
     * Extrae personajes dramáticos reales (parlantes o mudos).
     *
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    public function extractCharacters(string $text, array $existingCharacters = []): array
    {
        $payload = [
            'text' => $text,
            'existing_characters' => $existingCharacters,
        ];

        $result = $this->transport->callTool(
            'extract_characters',
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn ($t, $l, $s) => $t,
            fn ($t) => []
        );

        return (array) ($result['metadata']['characters'] ?? ($result['characters'] ?? []));
    }

    /**
     * Audita si un nombre corresponde a un personaje dramático real en uso.
     *
     * @param  array<int, string>|string  $sceneTexts
     * @return array{in_use: bool, is_dramatic_entity: bool, confidence: float, reason: string}
     */
    public function auditCharacterUsage(string $characterName, array|string $sceneTexts): array
    {
        $payload = [
            'character_name' => $characterName,
            'scene_texts' => is_array($sceneTexts) ? array_values($sceneTexts) : [$sceneTexts],
        ];

        $result = $this->transport->callTool(
            'audit_character_usage',
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn ($t, $l, $s) => $t,
            fn ($t) => []
        );

        return [
            'in_use' => (bool) ($result['metadata']['in_use'] ?? ($result['in_use'] ?? false)),
            'is_dramatic_entity' => (bool) ($result['metadata']['is_dramatic_entity'] ?? ($result['is_dramatic_entity'] ?? false)),
            'confidence' => (float) ($result['metadata']['confidence'] ?? ($result['confidence'] ?? 0.7)),
            'reason' => (string) ($result['metadata']['reason'] ?? ($result['reason'] ?? 'Auditoría completada.')),
        ];
    }
}
