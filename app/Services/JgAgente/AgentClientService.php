<?php

namespace App\Services\JgAgente;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AgentClientService
{
    protected string $baseUrl;

    protected string $secret;

    protected bool $mock;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.jg_agente.url', 'http://127.0.0.1:8001'), '/');
        $this->secret = (string) config('services.jg_agente.secret', 'filmmaker_dev_secret_key_12345');
        $this->mock = (bool) config('services.jg_agente.mock', false);
        $this->timeout = (int) config('services.jg_agente.timeout', 30);
    }

    /**
     * Genera las cabeceras canónicas HMAC-SHA256 para una petición HTTP.
     *
     * @return array<string, string>
     */
    public function generateSignatureHeaders(string $method, string $path, string $rawBody, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $bodySha256 = hash('sha256', $rawBody);
        $canonicalString = strtoupper($method)."\n".$path."\n".$timestamp."\n".$bodySha256;
        $signature = hash_hmac('sha256', $canonicalString, $this->secret);

        return [
            'X-Signature' => $signature,
            'X-Timestamp' => (string) $timestamp,
            'X-Correlation-ID' => Str::uuid()->toString(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Invoca una herramienta en jg_agente o recurre a la emulación local determinista.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTool(string $toolName, array $payload): array
    {
        if ($this->mock) {
            return $this->executeDeterministicFallback($toolName, $payload);
        }

        $path = "/api/v1/tools/{$toolName}";
        $url = "{$this->baseUrl}{$path}";
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $headers = $this->generateSignatureHeaders('POST', $path, $rawBody);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->withBody($rawBody, 'application/json')
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    return $data;
                }
            }

            // Si jg_agente devuelve error o 404/500, fallback determinista si se requiere resiliencia
            return $this->executeDeterministicFallback($toolName, $payload);
        } catch (ConnectionException $e) {
            // En caso de que el microservicio jg_agente no esté corriendo localmente
            return $this->executeDeterministicFallback($toolName, $payload);
        } catch (Exception $e) {
            return $this->executeDeterministicFallback($toolName, $payload);
        }
    }

    /**
     * Estructura notas y texto bruto en tratamiento dramático en presente indicativo.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>|null  $location
     * @param  array<string, mixed>  $userLocks
     */
    public function structureTreatment(
        string $rawText,
        array $characters = [],
        ?array $location = null,
        array $userLocks = []
    ): string {
        $payload = [
            'raw_text' => $rawText,
            'characters_context' => $characters,
            'location_context' => $location,
            'user_locks' => $userLocks,
        ];

        $result = $this->callTool('treatment_structuring', $payload);

        return (string) ($result['output'] ?? '');
    }

    /**
     * Convierte texto estructurado en guion literario canónico (Fountain/literario).
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<string, mixed>  $userLocks
     */
    public function formatScreenplay(
        string $structuredText,
        array $characters = [],
        array $userLocks = []
    ): string {
        $payload = [
            'structured_text' => $structuredText,
            'characters_context' => $characters,
            'user_locks' => $userLocks,
        ];

        $result = $this->callTool('screenplay_formatter', $payload);

        return (string) ($result['output'] ?? '');
    }

    /**
     * Desglosa el guion literario en planos técnicos con sintaxis '--- shot N (tipo) ---'.
     *
     * @return array{annotated_text: string, shots: array<int, array{shot_number: int, shot_type: string, description: string}>}
     */
    public function breakdownShots(string $screenplayText): array
    {
        $payload = [
            'screenplay_text' => $screenplayText,
        ];

        $result = $this->callTool('shot_breakdown_detector', $payload);

        $annotatedText = (string) ($result['output'] ?? '');
        $shots = (array) ($result['metadata']['shots'] ?? []);

        // Si metadata no incluye shots formateados, extraerlos directamente de annotated_text
        if (empty($shots) && ! empty($annotatedText)) {
            $shots = $this->parseShotsFromText($annotatedText);
        }

        return [
            'annotated_text' => $annotatedText,
            'shots' => $shots,
        ];
    }

    /**
     * Parsea planos anotados con el delimitador canónico '--- shot N (tipo) ---'.
     *
     * @return array<int, array{shot_number: int, shot_type: string, description: string}>
     */
    public function parseShotsFromText(string $text): array
    {
        $pattern = '/---\s*shot\s+(\d+)\s*\(([^)]+)\)\s*---\s*\n*(.*?)(?=(?:---\s*shot\s+\d+)|$)/is';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $shots = [];
        foreach ($matches as $match) {
            $shots[] = [
                'shot_number' => (int) $match[1],
                'shot_type' => strtoupper(trim($match[2])),
                'description' => trim($match[3]),
            ];
        }

        return $shots;
    }

    /**
     * Aplica los candados de usuario (pinned_terms y manual_diffs) a un texto.
     *
     * @param  array<string, mixed>  $userLocks
     */
    public function applyUserLocks(string $text, array $userLocks, string $stage): string
    {
        if (empty($text) || empty($userLocks)) {
            return $text;
        }

        $result = $text;

        // 1. Pinned terms
        $pinnedTerms = $userLocks['pinned_terms'] ?? [];
        if (is_array($pinnedTerms)) {
            foreach ($pinnedTerms as $term => $replacement) {
                if ($term !== '' && $replacement !== '') {
                    $pattern = '/\b'.preg_quote((string) $term, '/').'\b/iu';
                    $result = preg_replace($pattern, (string) $replacement, $result);
                }
            }
        }

        // 2. Manual diffs
        $manualDiffs = $userLocks['manual_diffs'] ?? [];
        if (is_array($manualDiffs)) {
            foreach ($manualDiffs as $diff) {
                if (! is_array($diff)) {
                    continue;
                }
                $diffStage = $diff['stage'] ?? null;
                if ($diffStage && $diffStage !== $stage) {
                    continue;
                }
                $original = $diff['original_ai'] ?? null;
                $override = $diff['user_override'] ?? null;
                if ($original && $override && str_contains($result, $original)) {
                    $result = str_replace($original, $override, $result);
                }
            }
        }

        return $result;
    }

    /**
     * Emulación determinista idéntica al contrato de jg_plugin_filmmaker.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function executeDeterministicFallback(string $toolName, array $payload): array
    {
        if ($toolName === 'treatment_structuring') {
            $rawText = trim((string) ($payload['raw_text'] ?? ''));
            $characters = (array) ($payload['characters_context'] ?? []);
            $location = $payload['location_context'] ?? null;
            $userLocks = (array) ($payload['user_locks'] ?? []);

            if (empty($rawText)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'structured']];
            }

            $prefix = '';
            if (is_array($location) && ! empty($location['name'])) {
                $locType = ! empty($location['type']) ? " ({$location['type']})" : '';
                $prefix = "En {$location['name']}{$locType}: ";
            }

            $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $rawText))));
            $structured = [];

            foreach ($paragraphs as $i => $para) {
                $cleaned = preg_replace('/\s+/', ' ', $para);

                foreach ($characters as $char) {
                    if (is_array($char) && ! empty($char['name'])) {
                        $cname = $char['name'];
                        $pattern = '/\b'.preg_quote($cname, '/').'\b/i';
                        $cleaned = preg_replace($pattern, $cname, $cleaned);
                    }
                }

                if ($i === 0 && $prefix !== '' && ! str_starts_with($cleaned, 'En ')) {
                    $cleaned = $prefix.$cleaned;
                }

                $structured[] = $cleaned;
            }

            $output = implode("\n\n", $structured);
            $output = $this->applyUserLocks($output, $userLocks, 'structured');

            return [
                'success' => true,
                'output' => $output,
                'metadata' => [
                    'stage' => 'structured',
                    'applied_locks_count' => count($userLocks['pinned_terms'] ?? []) + count($userLocks['manual_diffs'] ?? []),
                ],
            ];
        }

        if ($toolName === 'screenplay_formatter') {
            $structuredText = trim((string) ($payload['structured_text'] ?? ''));
            $characters = (array) ($payload['characters_context'] ?? []);
            $userLocks = (array) ($payload['user_locks'] ?? []);

            if (empty($structuredText)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'screenplay']];
            }

            $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $structuredText))));
            $lines = [];

            // Añadir encabezado estándar si no tiene ya INT/EXT
            $firstPara = $paragraphs[0] ?? '';
            if (! preg_match('/^(INT|EXT)\./i', $firstPara)) {
                $lines[] = 'INT. LOCALIZACION - DIA';
                $lines[] = '';
            }

            foreach ($paragraphs as $para) {
                // Detectar si el párrafo empieza por el nombre de un personaje seguido de dos puntos o acción
                $matchedChar = null;
                foreach ($characters as $char) {
                    if (is_array($char) && ! empty($char['name'])) {
                        $cname = $char['name'];
                        if (preg_match('/^'.preg_quote($cname, '/').':?\s*(.*)$/i', $para, $m)) {
                            $matchedChar = strtoupper($cname);
                            $speech = trim($m[1]);
                            $lines[] = $matchedChar;
                            $lines[] = $speech !== '' ? $speech : '...';
                            $lines[] = '';
                            break;
                        }
                    }
                }

                if (! $matchedChar) {
                    $lines[] = $para;
                    $lines[] = '';
                }
            }

            $output = rtrim(implode("\n", $lines));
            $output = $this->applyUserLocks($output, $userLocks, 'screenplay');

            return [
                'success' => true,
                'output' => $output,
                'metadata' => [
                    'stage' => 'screenplay',
                    'format' => 'fountain',
                ],
            ];
        }

        if ($toolName === 'shot_breakdown_detector') {
            $screenplayText = trim((string) ($payload['screenplay_text'] ?? ''));

            if (empty($screenplayText)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'shots', 'shots' => []]];
            }

            $blocks = array_values(array_filter(array_map('trim', explode("\n\n", $screenplayText))));
            $annotated = [];
            $shots = [];
            $types = ['PG', 'PM', 'PP', 'PD'];

            $shotNum = 1;
            foreach ($blocks as $block) {
                if (preg_match('/^(INT|EXT)\./i', $block)) {
                    $annotated[] = "--- shot {$shotNum} (PG) ---\n{$block}";
                    $shots[] = [
                        'shot_number' => $shotNum,
                        'shot_type' => 'PG',
                        'description' => $block,
                    ];
                    $shotNum++;
                } else {
                    $type = $types[($shotNum - 1) % count($types)];
                    $annotated[] = "--- shot {$shotNum} ({$type}) ---\n{$block}";
                    $shots[] = [
                        'shot_number' => $shotNum,
                        'shot_type' => $type,
                        'description' => $block,
                    ];
                    $shotNum++;
                }
            }

            $annotatedText = implode("\n\n", $annotated);

            return [
                'success' => true,
                'output' => $annotatedText,
                'metadata' => [
                    'stage' => 'shots',
                    'shots' => $shots,
                    'total_shots' => count($shots),
                ],
            ];
        }

        return ['success' => false, 'output' => '', 'error' => "Herramienta desconocida: {$toolName}"];
    }
}
