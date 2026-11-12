<?php

namespace App\Services\JgAgente;

use App\Services\JgAgente\Clients\CharacterAgentClient;
use App\Services\JgAgente\Clients\ContinuityAgentClient;
use App\Services\JgAgente\Clients\ScriptAgentClient;
use App\Services\JgAgente\Clients\StoryboardAgentClient;
use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;

class AgentClientService
{
    protected HmacSigner $signer;

    protected TelemetryRecorder $telemetry;

    protected DeterministicFallbackEngine $fallbackEngine;

    protected AgentHttpTransport $transport;

    protected ScriptAgentClient $scriptClient;

    protected StoryboardAgentClient $storyboardClient;

    protected ContinuityAgentClient $continuityClient;

    protected CharacterAgentClient $characterClient;

    public function __construct(
        ?AgentHttpTransport $transport = null,
        ?HmacSigner $signer = null,
        ?TelemetryRecorder $telemetry = null,
        ?DeterministicFallbackEngine $fallbackEngine = null,
        ?ScriptAgentClient $scriptClient = null,
        ?StoryboardAgentClient $storyboardClient = null,
        ?ContinuityAgentClient $continuityClient = null,
        ?CharacterAgentClient $characterClient = null
    ) {
        $this->signer = $signer ?? new HmacSigner;
        $this->telemetry = $telemetry ?? new TelemetryRecorder;
        $this->fallbackEngine = $fallbackEngine ?? new DeterministicFallbackEngine;
        $this->transport = $transport ?? new AgentHttpTransport;

        $this->scriptClient = $scriptClient ?? new ScriptAgentClient(
            $this->transport,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry
        );

        $this->storyboardClient = $storyboardClient ?? new StoryboardAgentClient(
            $this->transport,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry
        );

        $this->continuityClient = $continuityClient ?? new ContinuityAgentClient(
            $this->transport,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry
        );

        $this->characterClient = $characterClient ?? new CharacterAgentClient(
            $this->transport,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry
        );
    }

    /**
     * Genera las cabeceras canónicas HMAC-SHA256 para una petición HTTP.
     *
     * @return array<string, string>
     */
    public function generateSignatureHeaders(
        string $method,
        string $path,
        string $rawBody,
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $correlationId = null
    ): array {
        return $this->signer->generateSignatureHeaders(
            $method,
            $path,
            $rawBody,
            $this->transport->getSecret(),
            $timestamp,
            $nonce,
            $correlationId
        );
    }

    /**
     * Invoca una herramienta en jg_agente o recurre a la emulación local determinista.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTool(string $toolName, array $payload): array
    {
        return $this->transport->callTool(
            $toolName,
            $payload,
            $this->signer,
            $this->fallbackEngine,
            $this->telemetry,
            fn (string $text, array $locks, string $stage) => $this->scriptClient->applyUserLocks($text, $locks, $stage),
            fn (string $text) => $this->storyboardClient->parseShotsFromText($text)
        );
    }

    /**
     * Obtiene los metadatos y trazabilidad de la última invocación de IA.
     *
     * @return array<string, mixed>|null
     */
    public function getLastTelemetry(): ?array
    {
        return $this->telemetry->getLastTelemetry();
    }

    /**
     * Estructura notas y texto bruto en tratamiento dramático en presente indicativo.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>|null  $location
     * @param  array<string, mixed>|string  $userLocks
     */
    public function structureTreatment(
        string $rawText,
        array $characters = [],
        ?array $location = null,
        array|string $userLocks = []
    ): string {
        return $this->scriptClient->structureTreatment($rawText, $characters, $location, $userLocks);
    }

    /**
     * Convierte texto estructurado en guion literario canónico Fountain.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>|string  $userLocks
     * @param  array<string, mixed>|null  $location
     */
    public function formatScreenplay(
        string $structuredText,
        array $characters = [],
        array|string $userLocks = [],
        ?array $location = null
    ): string {
        return $this->scriptClient->formatScreenplay($structuredText, $characters, $userLocks, $location);
    }

    /**
     * Desglosa el guion literario en planos técnicos con sintaxis '--- shot N (tipo) ---'.
     *
     * @return array{annotated_text: string, shots: array<int, array{shot_number: int, shot_type: string, description: string}>}
     */
    public function breakdownShots(string $screenplayText): array
    {
        return $this->storyboardClient->breakdownShots($screenplayText);
    }

    /**
     * Audita y detecta inconsistencias dramáticas y agujeros de guión en la secuencia de bloques del proyecto.
     *
     * @param  array<int, array<string, mixed>>  $blocksData
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<int, array<string, mixed>>  $locations
     * @return array{detected_holes: array<int, array{block_id: string|int, reason: string}>, total_holes: int}
     */
    public function auditPlotHoles(array $blocksData, array $characters = [], array $locations = []): array
    {
        return $this->continuityClient->auditPlotHoles(
            $blocksData,
            $characters,
            $locations,
            fn (string $tool, array $payload) => $this->callTool($tool, $payload)
        );
    }

    /**
     * Extrae personajes dramáticos reales (parlantes o mudos).
     *
     * @param  array<int, array<string, mixed>>  $existingCharacters
     * @return array<int, array<string, mixed>>
     */
    public function extractCharacters(string $text, array $existingCharacters = []): array
    {
        return $this->characterClient->extractCharacters($text, $existingCharacters);
    }

    /**
     * Audita si un nombre corresponde a un personaje dramático real en uso.
     *
     * @param  array<int, string>|string  $sceneTexts
     * @return array{in_use: bool, is_dramatic_entity: bool, confidence: float, reason: string}
     */
    public function auditCharacterUsage(string $characterName, array|string $sceneTexts): array
    {
        return $this->characterClient->auditCharacterUsage($characterName, $sceneTexts);
    }

    /**
     * Parsea planos anotados con el delimitador canónico '--- shot N (tipo) ---'.
     *
     * @return array<int, array{shot_number: int, shot_type: string, description: string}>
     */
    public function parseShotsFromText(string $text): array
    {
        return $this->storyboardClient->parseShotsFromText($text);
    }

    /**
     * Aplica los candados de usuario (pinned_terms y manual_diffs) a un texto.
     *
     * @param  array<string, mixed>  $userLocks
     */
    public function applyUserLocks(string $text, array $userLocks, string $stage): string
    {
        return $this->scriptClient->applyUserLocks($text, $userLocks, $stage);
    }
}
