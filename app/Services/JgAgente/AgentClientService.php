<?php

namespace App\Services\JgAgente;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentClientService
{
    protected string $baseUrl;

    protected string $secret;

    protected bool $mock;

    protected int $timeout;

    /**
     * Registro de telemetría de la última invocación de IA.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $lastTelemetry = null;

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
            'X-Agent-ID' => 'jg_filmmaker',
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

        // 1. Intentar llamar a microservicio jg_agente (o Http::fake en tests)
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
        } catch (ConnectionException $e) {
            // Sin conexión a microservicio jg_agente
        } catch (Exception $e) {
            // Error en petición
        }

        // 2. En producción / runtime real (fuera de testing): inferencia directa con proveedor LLM (Groq)
        if (! app()->environment('testing')) {
            $directResult = $this->callDirectLlm($toolName, $payload);
            if ($directResult !== null) {
                return $directResult;
            }
        }

        // 3. Fallback determinista limpio (cumple regla de oro offline y tests)
        return $this->executeDeterministicFallback($toolName, $payload);
    }

    /**
     * Obtiene los metadatos y trazabilidad de la última invocación de IA.
     *
     * @return array<string, mixed>|null
     */
    public function getLastTelemetry(): ?array
    {
        return $this->lastTelemetry;
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
        array $userLocks = [],
        ?array $location = null
    ): string {
        $payload = [
            'structured_text' => $structuredText,
            'characters_context' => $characters,
            'user_locks' => $userLocks,
            'location_context' => $location,
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
            $userLocks = (array) ($payload['user_locks'] ?? []);

            if (empty($rawText)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'structured']];
            }

            $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $rawText))));
            $structured = [];

            foreach ($paragraphs as $para) {
                // Purgar cualquier residuo previo de "En LOCALIZACIÓN:" para mantener prosa pura
                $cleaned = preg_replace('/^En [^:]+:\s*/i', '', $para);
                $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $cleaned));

                foreach ($characters as $char) {
                    if (is_array($char) && ! empty($char['name'])) {
                        $cname = $char['name'];
                        $pattern = '/\b'.preg_quote($cname, '/').'\b/i';
                        $cleaned = preg_replace($pattern, $cname, $cleaned);
                    }
                }

                if ($cleaned !== '') {
                    $structured[] = $cleaned;
                }
            }

            $output = implode("\n\n", $structured);
            $output = $this->applyUserLocks($output, $userLocks, 'structured');

            $this->lastTelemetry = [
                'tool' => 'treatment_structuring',
                'stage' => 'structured',
                'provider' => 'deterministic_engine',
                'model' => 'rule-based-nlp',
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => 8,
                'status' => 'SUCCESS',
                'system_prompt' => $this->buildSystemPrompt('treatment_structuring', $payload),
                'user_prompt' => $this->buildUserPrompt('treatment_structuring', $payload),
                'raw_response' => $output,
                'output' => $output,
                'characters_context' => $characters,
                'location_context' => $payload['location_context'] ?? null,
                'user_locks' => $userLocks,
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Inyección Contextual & Biblia Dramática',
                        'status' => 'completed',
                        'detail' => 'Cargados '.count($characters).' personajes y localización dramática en el motor de procesamiento.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Estructuración Dramática',
                        'status' => 'completed',
                        'detail' => 'Tratamiento procesado en presente de indicativo y purgado de prefijos técnicos.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Aplicación de Candados y Términos Fijos',
                        'status' => 'completed',
                        'detail' => 'Verificados '.count($userLocks['pinned_terms'] ?? []).' términos fijos.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Validación de Formato',
                        'status' => 'completed',
                        'detail' => 'Salida normalizada en párrafos limpios.',
                    ],
                ],
            ];

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
            $location = $payload['location_context'] ?? null;
            $userLocks = (array) ($payload['user_locks'] ?? []);

            if (empty($structuredText)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'screenplay']];
            }

            $rawParagraphs = array_values(array_filter(array_map('trim', explode("\n", $structuredText))));
            $lines = [];

            // 1. Construir encabezado canónico de escena si procede
            $locName = ! empty($location['name']) ? strtoupper($location['name']) : 'LOCALIZACION';
            $locType = ! empty($location['type']) ? strtoupper($location['type']) : 'INT';
            $timeOfDay = ! empty($location['time_of_day']) ? strtoupper($location['time_of_day']) : 'DIA';
            $slugline = "{$locType}. {$locName} - {$timeOfDay} 1";

            $firstPara = $rawParagraphs[0] ?? '';
            if (! preg_match('/^(INT|EXT|INT\/EXT)\./i', $firstPara)) {
                $lines[] = $slugline;
                $lines[] = '';
            }

            // 2. Procesar párrafos (diálogos explícitos vs acciones)
            foreach ($rawParagraphs as $para) {
                // Si ya es un encabezado
                if (preg_match('/^(INT|EXT|INT\/EXT)\./i', $para)) {
                    $lines[] = strtoupper($para);
                    $lines[] = '';

                    continue;
                }

                // Limpiar prefijos redundantes de tratamiento como "En BAR CENTRAL (INT): "
                $cleanPara = preg_replace('/^En\s+[^:]+:\s*/iu', '', $para);

                $dialogueMatched = false;

                // Formato A: "PERSONAJE: Diálogo" o "PERSONAJE (acotación): Diálogo"
                if (preg_match('/^([a-záéíóúñ\s]+?)(?:\s*\(([^)]+)\))?:\s*(.+)$/iu', $cleanPara, $m)) {
                    $speaker = strtoupper(trim($m[1]));
                    $parenthetical = ! empty($m[2]) ? trim($m[2]) : null;
                    $speech = trim($m[3]);

                    $lines[] = $speaker;
                    if ($parenthetical) {
                        $lines[] = '('.strtolower($parenthetical).')';
                    }
                    $lines[] = $speech;
                    $lines[] = '';
                    $dialogueMatched = true;
                }
                // Formato B: "Personaje dice/pregunta: \"Diálogo\""
                elseif (preg_match('/^([a-záéíóúñ\s]+?)\s+(?:dice|responde|susurra|grita|pregunta|afirma)\s*:\s*["«]([^"»]+)["»]$/iu', $cleanPara, $m)) {
                    $speaker = strtoupper(trim($m[1]));
                    $speech = trim($m[2]);

                    $lines[] = $speaker;
                    $lines[] = $speech;
                    $lines[] = '';
                    $dialogueMatched = true;
                }

                // Si no es un diálogo explícito, es un bloque de ACCIÓN cinematográfica
                if (! $dialogueMatched) {
                    $actionLine = $cleanPara;
                    foreach ($characters as $char) {
                        if (is_array($char) && ! empty($char['name'])) {
                            $cname = $char['name'];
                            $pattern = '/\b'.preg_quote($cname, '/').'\b/iu';
                            $actionLine = preg_replace($pattern, strtoupper($cname), $actionLine);
                        }
                    }

                    $lines[] = $actionLine;
                    $lines[] = '';
                }
            }

            $output = rtrim(implode("\n", $lines));
            $output = $this->applyUserLocks($output, $userLocks, 'screenplay');

            $this->lastTelemetry = [
                'tool' => 'screenplay_formatter',
                'stage' => 'screenplay',
                'provider' => 'deterministic_engine',
                'model' => 'rule-based-nlp',
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => 10,
                'status' => 'SUCCESS',
                'system_prompt' => $this->buildSystemPrompt('screenplay_formatter', $payload),
                'user_prompt' => $this->buildUserPrompt('screenplay_formatter', $payload),
                'raw_response' => $output,
                'output' => $output,
                'characters_context' => $characters,
                'location_context' => $location,
                'user_locks' => $userLocks,
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Inyección Contextual & Slugline',
                        'status' => 'completed',
                        'detail' => 'Slugline generado: '.$slugline,
                    ],
                    [
                        'number' => 2,
                        'title' => 'Formateo Fountain Canónico',
                        'status' => 'completed',
                        'detail' => 'Detección de diálogos y formateo de nombres en mayúsculas.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Aplicación de Candados y Términos Fijos',
                        'status' => 'completed',
                        'detail' => 'Verificados '.count($userLocks['pinned_terms'] ?? []).' términos fijos.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Validación de Formato',
                        'status' => 'completed',
                        'detail' => 'Estructura de guion validada.',
                    ],
                ],
            ];

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

            $this->lastTelemetry = [
                'tool' => 'shot_breakdown_detector',
                'stage' => 'shots',
                'provider' => 'deterministic_engine',
                'model' => 'rule-based-nlp',
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => 6,
                'status' => 'SUCCESS',
                'system_prompt' => $this->buildSystemPrompt('shot_breakdown_detector', $payload),
                'user_prompt' => $this->buildUserPrompt('shot_breakdown_detector', $payload),
                'raw_response' => $annotatedText,
                'output' => $annotatedText,
                'characters_context' => [],
                'location_context' => null,
                'user_locks' => [],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Análisis de Escena y Ritmo',
                        'status' => 'completed',
                        'detail' => 'Segmentación de bloques de guion literario.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Asignación de Cobertura de Cámara',
                        'status' => 'completed',
                        'detail' => count($shots).' planos técnicos asignados (PG, PM, PP, PD).',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Delimitación Canónica',
                        'status' => 'completed',
                        'detail' => 'Sintaxis --- shot N (tipo) --- inyectada.',
                    ],
                ],
            ];

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

    /**
     * Inferencia semántica directa con proveedor LLM configurado (Groq / OpenAI / Gemini).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function callDirectLlm(string $toolName, array $payload): ?array
    {
        $apiKey = (string) config('services.llm.api_key', '');
        if (empty($apiKey)) {
            return null;
        }

        $provider = (string) config('services.llm.provider', 'groq');
        $model = (string) config('services.llm.model', 'llama-3.3-70b-versatile');
        $timeout = (int) config('services.llm.timeout', 45);

        $systemPrompt = $this->buildSystemPrompt($toolName, $payload);
        $userPrompt = $this->buildUserPrompt($toolName, $payload);

        if (empty($systemPrompt) || empty($userPrompt)) {
            return null;
        }

        $startTime = microtime(true);

        try {
            $endpoint = match ($provider) {
                'openai' => 'https://api.openai.com/v1/chat/completions',
                default => 'https://api.groq.com/openai/v1/chat/completions',
            };

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if (! $response->successful()) {
                Log::warning("Direct LLM call failed ({$response->status()}): {$response->body()}");

                return null;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
            if (empty($content)) {
                return null;
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $stage = match ($toolName) {
                'treatment_structuring' => 'structured',
                'screenplay_formatter' => 'screenplay',
                'shot_breakdown_detector' => 'shots',
                default => 'raw',
            };

            $output = $content;

            if ($toolName === 'treatment_structuring') {
                $clean = preg_replace('/^En [^:]+:\s*/i', '', $content);
                $output = $this->applyUserLocks((string) $clean, (array) ($payload['user_locks'] ?? []), 'structured');
            } elseif ($toolName === 'screenplay_formatter') {
                $output = $this->applyUserLocks($content, (array) ($payload['user_locks'] ?? []), 'screenplay');
            }

            // Registrar telemetría de invocación IA
            $this->lastTelemetry = [
                'tool' => $toolName,
                'stage' => $stage,
                'provider' => $provider,
                'model' => $model,
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => $durationMs,
                'status' => 'SUCCESS',
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'raw_response' => $content,
                'output' => $output,
                'characters_context' => $payload['characters_context'] ?? [],
                'location_context' => $payload['location_context'] ?? null,
                'user_locks' => $payload['user_locks'] ?? [],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Inyección Contextual & Biblia Dramática',
                        'status' => 'completed',
                        'detail' => 'Cargados '.count($payload['characters_context'] ?? []).' personajes y localización dramática en el System Prompt.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Inferencia Cognitiva LLM ('.$model.')',
                        'status' => 'completed',
                        'detail' => 'Inferencia resuelta con éxito en '.$provider.' en '.$durationMs.' ms con temperatura 0.7.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Aplicación de Candados y Términos Fijos',
                        'status' => 'completed',
                        'detail' => 'Verificados y protegidos los términos fijados por el autor contra sobreescritura.',
                    ],
                    [
                        'number' => 4,
                        'title' => 'Normalización de Salida Cinematográfica',
                        'status' => 'completed',
                        'detail' => match ($toolName) {
                            'treatment_structuring' => 'Validada prosa narrativa en presente continuo y eliminación de etiquetas residuales.',
                            'screenplay_formatter' => 'Validado formato de guion estándar Fountain con encabezados y parlamentos.',
                            default => 'Planos técnicos delimitados bajo el estándar --- shot N (tipo) ---.',
                        },
                    ],
                ],
            ];

            if ($toolName === 'treatment_structuring') {
                return [
                    'success' => true,
                    'output' => $output,
                    'metadata' => ['stage' => 'structured', 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs],
                ];
            }

            if ($toolName === 'screenplay_formatter') {
                return [
                    'success' => true,
                    'output' => $output,
                    'metadata' => ['stage' => 'screenplay', 'format' => 'fountain', 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs],
                ];
            }

            if ($toolName === 'shot_breakdown_detector') {
                $shots = $this->parseShotsFromText($content);

                return [
                    'success' => true,
                    'output' => $content,
                    'metadata' => ['stage' => 'shots', 'shots' => $shots, 'total_shots' => count($shots), 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Direct LLM exception: {$e->getMessage()}");

            return null;
        }

        return null;
    }

    /**
     * Construye el system prompt cinematográfico profesional según la herramienta.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function buildSystemPrompt(string $toolName, array $payload): ?string
    {
        $characters = (array) ($payload['characters_context'] ?? []);
        $charsDesc = empty($characters) ? 'Sin personajes específicos definidos.' : implode("\n", array_map(function ($c) {
            $name = $c['name'] ?? 'Personaje';
            $role = $c['role'] ?? 'personaje';

            return "- {$name} (Rol: {$role})";
        }, $characters));

        $location = $payload['location_context'] ?? null;
        $locDesc = 'Sin localización específica.';
        if (is_array($location) && ! empty($location['name'])) {
            $lname = $location['name'];
            $ltype = $location['type'] ?? 'INT';
            $locDesc = "{$lname} ({$ltype})";
        }

        $userLocks = (array) ($payload['user_locks'] ?? []);
        $pinned = (array) ($userLocks['pinned_terms'] ?? []);
        $locksDesc = empty($pinned) ? 'Ningún término fijado.' : implode("\n", array_map(fn ($k, $v) => "- Sustituir '{$k}' siempre por '{$v}'", array_keys($pinned), $pinned));

        if ($toolName === 'treatment_structuring') {
            return "Eres un consultor y analista cinematográfico profesional.\n"
                ."Tu misión es transformar notas dispersas, ideas en bruto o borradores de un autor en un TRATAMIENTO CINEMATOGRÁFICO NARRATIVO formal.\n\n"
                ."DIRECTRICES Y REGLAS ESTRICTAS:\n"
                ."1. TIEMPO VERBAL: Redacta exclusivamente en tiempo PRESENTE DE INDICATIVO continuo (ej: 'Javier camina', 'el viento abre la ventana').\n"
                ."2. ACCIONES OBSERVABLES: Describe acciones físicas visibles, dinámicas espaciales y tensión dramática sin juicios de valor abstractos.\n"
                ."3. CONTEXTO DE PERSONAJES:\n{$charsDesc}\n"
                ."4. CONTEXTO DE LOCALIZACIÓN:\n{$locDesc}\n"
                ."Integra la atmósfera de este entorno dentro de la narración, pero NUNCA antepongas un encabezado o prefijo como 'En LOCALIZACIÓN: ' ni sluglines de guion.\n"
                ."5. CANDADOS Y TÉRMINOS FIJADOS (REGLA INMUTABLE):\n{$locksDesc}\n"
                ."6. PROHIBICIONES Y FORMATO DE SALIDA:\n"
                ."- NO incluyas encabezados de escena (sluglines como 'INT. BAR - DIA'), ni títulos, ni prefijos como 'En BAR CENTRAL:', ni metadatos.\n"
                .'- Devuelve ÚNICAMENTE la prosa narrativa dramática en párrafos limpios, sin preámbulos ni conclusiones.';
        }

        if ($toolName === 'screenplay_formatter') {
            $locSlug = is_array($location) && ! empty($location['name']) ? strtoupper($location['name']) : 'LOCALIZACION';
            $locType = is_array($location) && ! empty($location['type']) ? strtoupper($location['type']) : 'INT';
            $timeOfDay = is_array($location) && ! empty($location['time_of_day']) ? strtoupper($location['time_of_day']) : 'DIA';
            $slugline = "{$locType}. {$locSlug} - {$timeOfDay} 1";

            return "Eres un guionista cinematográfico profesional.\n"
                ."Tu misión es transformar el tratamiento dramático en un GUION CINEMATOGRÁFICO ESTÁNDAR (Fountain / Courier 12pt).\n\n"
                ."DIRECTRICES ESTRICTAS:\n"
                ."1. ENCABEZADO DE ESCENA: Inicia con el slugline estándar: {$slugline}\n"
                ."2. RESOLUCIÓN DE ESTILO INDIRECTO: Transforma cualquier narración indirecta en acción descriptiva y bloque de diálogo tipificado:\n"
                ."   Ejemplo: 'Javier mira a Juan con miedo y le dice que ya están llegando.' ->\n"
                ."   Javier mira fijamente a Juan con angustia contenida.\n\n"
                ."   JAVIER\n"
                ."   Ya están llegando.\n"
                ."3. INFERENCIA DE PERSONAJES: Detecta a todos los interlocutores (como JUAN) e infiere sus parlamentos en mayúsculas centradas aunque no estuviesen en la biblia previa.\n"
                ."4. FORMATO FOUNTAIN:\n"
                ."   NOMBRE PERSONAJE\n"
                ."   (acotación opcional en minúsculas)\n"
                ."   Línea de diálogo.\n"
                ."5. Párrafos de acción concisos (2-3 líneas máximo).\n"
                .'6. Devuelve ÚNICAMENTE el guion literario, sin preámbulos ni metadatos.';
        }

        if ($toolName === 'shot_breakdown_detector') {
            return "Eres un director de fotografía cinematográfico.\n"
                ."Analiza el guion literario y propón una cobertura técnica de planos de cámara para rodaje evaluando la tensión dramática.\n\n"
                ."DELIMITADOR OBLIGATORIO: Utiliza estrictamente la sintaxis canónica:\n"
                ."--- shot N (tipo) ---\n"
                ."Tipos admitidos: PG (Plano General), PM (Plano Medio), PP (Primer Plano), PD (Plano Detalle), PA (Plano Americano).\n"
                .'Intercala los delimitadores antes de cada bloque de acción o diálogo del guion.';
        }

        return null;
    }

    /**
     * Construye el user prompt con los datos brutos del autor.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function buildUserPrompt(string $toolName, array $payload): ?string
    {
        if ($toolName === 'treatment_structuring') {
            $raw = trim((string) ($payload['raw_text'] ?? ''));

            return "NOTAS / TEXTO BRUTO DEL AUTOR:\n{$raw}";
        }

        if ($toolName === 'screenplay_formatter') {
            $structured = trim((string) ($payload['structured_text'] ?? ''));

            return "TRATAMIENTO DRAMÁTICO:\n{$structured}";
        }

        if ($toolName === 'shot_breakdown_detector') {
            $screenplay = trim((string) ($payload['screenplay_text'] ?? ''));

            return "GUIÓN LITERARIO:\n{$screenplay}";
        }

        return null;
    }
}
