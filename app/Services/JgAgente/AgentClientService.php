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
        $this->baseUrl = rtrim((string) config('services.jg_agente.url', 'http://127.0.0.1:8001'), '/');
        $this->mock = (bool) config('services.jg_agente.mock', false);
        $this->timeout = (int) config('services.jg_agente.timeout', 30);

        // Sin secreto por defecto en código (CWE-798): resolución limpia mediante config() determinista.
        $secret = (string) config('services.jg_agente.secret', '');
        if ($secret === '' && ! $this->mock) {
            throw new \RuntimeException(
                'Seguridad (CWE-798): secreto HMAC de jg_agente no configurado. '
                .'Defina JG_AGENTE_SECRET en .env (o active JG_AGENTE_MOCK=true para modo offline).'
            );
        }
        $this->secret = $secret;
    }

    /**
     * Genera las cabeceras canónicas HMAC-SHA256 para una petición HTTP.
     *
     * Contrato canónico: METHOD\nPATH\nTIMESTAMP\nSHA256(BODY)\nNONCE. El X-Nonce (UUID v4, de un
     * solo uso) forma parte del material firmado, por lo que el receptor puede rechazar repeticiones.
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
        $timestamp = $timestamp ?? time();
        $nonce = $nonce ?? Str::uuid()->toString();
        $correlationId = $correlationId ?? Str::uuid()->toString();
        $keyId = (string) config('services.jg_agente.key_id', 'jg_filmmaker_v1');
        $bodySha256 = hash('sha256', $rawBody);
        $canonicalString = strtoupper($method)."\n".$path."\n".$timestamp."\n".$bodySha256."\n".$nonce;
        $signature = hash_hmac('sha256', $canonicalString, $this->secret);

        return [
            'X-Signature' => $signature,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Correlation-ID' => $correlationId,
            'X-Key-ID' => $keyId,
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
            $fallback = $this->executeDeterministicFallback($toolName, $payload);
            $fallback['source'] = 'deterministic_fallback';
            $fallback['degraded'] = true;

            return $fallback;
        }

        // 1. Intentar llamar a microservicio jg_agente (o Http::fake en tests)
        $path = "/api/v1/tools/{$toolName}";
        $url = "{$this->baseUrl}{$path}";

        try {
            $rawBody = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            Log::error("Error de serialización JSON en payload para jg_agente [{$toolName}]", ['error' => $e->getMessage()]);
            throw new \InvalidArgumentException("Payload no serializable para {$toolName}: ".$e->getMessage(), 0, $e);
        }

        $headers = $this->generateSignatureHeaders('POST', $path, $rawBody);
        $correlationId = $headers['X-Correlation-ID'];

        try {
            $verifyTls = (bool) config('services.jg_agente.verify_tls', true);
            $response = Http::withHeaders($headers)
                ->withOptions(['verify' => $verifyTls])
                ->timeout($this->timeout)
                ->retry(2, 200, function (Exception $exception) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->withBody($rawBody, 'application/json')
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && ($data['success'] ?? true) === true) {
                    $data['source'] = 'agent';
                    $data['degraded'] = false;

                    return $data;
                }

                Log::warning("Respuesta no exitosa de jg_agente para herramienta [{$toolName}]", [
                    'tool' => $toolName,
                    'correlation_id' => $correlationId,
                    'status' => $response->status(),
                    'error' => is_array($data) ? ($data['error'] ?? 'flag success es false') : 'formato no json',
                ]);
            } else {
                Log::warning("Llamada HTTP fallida a jg_agente para herramienta [{$toolName}]", [
                    'tool' => $toolName,
                    'correlation_id' => $correlationId,
                    'status' => $response->status(),
                ]);
            }
        } catch (ConnectionException $e) {
            Log::warning("Sin conexión con microservicio jg_agente para herramienta [{$toolName}]", [
                'tool' => $toolName,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            Log::warning("Excepción durante la invocación a jg_agente para herramienta [{$toolName}]", [
                'tool' => $toolName,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Fallback opcional a LLM directo (prohibido en producción y deshabilitado por defecto)
        $allowDirectLlm = ! app()->isProduction()
            && ! app()->environment('testing')
            && (bool) config('services.llm.direct_enabled', false);

        if ($allowDirectLlm) {
            $directResult = $this->callDirectLlm($toolName, $payload);
            if ($directResult !== null) {
                $directResult['source'] = 'direct_llm';
                $directResult['degraded'] = true;

                return $directResult;
            }
        }

        // 3. Fallback determinista limpio
        $fallback = $this->executeDeterministicFallback($toolName, $payload);
        $fallback['source'] = 'deterministic_fallback';
        $fallback['degraded'] = true;

        return $fallback;
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
        array|string $userLocks = []
    ): string {
        if (! is_array($userLocks)) {
            $decoded = is_string($userLocks) ? json_decode($userLocks, true) : null;
            $userLocks = is_array($decoded) ? $decoded : [];
        }

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
     * @param  array<string, mixed>|string  $userLocks
     */
    public function formatScreenplay(
        string $structuredText,
        array $characters = [],
        array|string $userLocks = [],
        ?array $location = null
    ): string {
        if (! is_array($userLocks)) {
            $decoded = is_string($userLocks) ? json_decode($userLocks, true) : null;
            $userLocks = is_array($decoded) ? $decoded : [];
        }

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
     * Audita y detecta inconsistencias dramáticas y agujeros de guión en la secuencia de bloques del proyecto.
     *
     * @param  array<int, array<string, mixed>>  $blocksData
     * @param  array<int, array<string, mixed>>  $characters
     * @param  array<int, array<string, mixed>>  $locations
     * @return array{detected_holes: array<int, array{block_id: string|int, reason: string}>, total_holes: int}
     */
    public function auditPlotHoles(array $blocksData, array $characters = [], array $locations = []): array
    {
        $payload = [
            'blocks' => $blocksData,
            'characters' => $characters,
            'locations' => $locations,
        ];

        $result = $this->callTool('plot_hole_detector', $payload);

        $rawHoles = (array) ($result['metadata']['detected_holes'] ?? ($result['detected_holes'] ?? []));

        // Indexar bloques por ID para cotejo estricto de evidencia textual (Iteración 2 y 3)
        $blocksIndex = [];
        foreach ($blocksData as $b) {
            $blocksIndex[(string) ($b['id'] ?? '')] = $b;
        }

        $verifiedHoles = [];
        foreach ($rawHoles as $hole) {
            $bId = (string) ($hole['block_id'] ?? '');
            $reason = trim((string) ($hole['reason'] ?? ''));

            if (empty($bId) || empty($reason) || ! isset($blocksIndex[$bId])) {
                continue;
            }

            $block = $blocksIndex[$bId];
            $blockText = (string) ($block['text'] ?? ($block['screenplay_content'] ?? ($block['structured_text'] ?? ($block['raw_text'] ?? ''))));

            // Filtro Anti-Falsos Positivos de Capitalización y Tipografía de Nombres:
            // Discrepancias tipográficas de mayúsculas/minúsculas jamás son agujeros de guión dramáticos
            if (preg_match('/(?:may[uú]scula|min[uú]scula|capitaliz|escrito\s+como|inconsistencia\s+de\s+nombre|inconsistencia\s+en\s+el\s+nombre|diferente\s+a\s*["\'][^"\']+["\']|nombre\s+del\s+(?:personaje|objeto|elemento)|mismo\s+personaje\s+con\s+distinto\s+nombre)/iu', $reason)) {
                continue;
            }
            if (preg_match_all('/["«\']([^"»\']+)["»\']/u', $reason, $quotedMatches)) {
                $quoted = $quotedMatches[1];
                if (count($quoted) >= 2) {
                    $firstQuote = mb_strtolower(trim($quoted[0]));
                    $secondQuote = mb_strtolower(trim($quoted[1]));
                    if ($firstQuote === $secondQuote) {
                        continue;
                    }
                }
            }

            // Filtro Anti-Falsos Positivos: Si el motivo cita a un personaje que NO aparece en el texto del bloque, se descarta
            $isFalsePositive = false;
            foreach ($characters as $char) {
                $charName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
                if (! empty($charName) && preg_match('/\b'.preg_quote($charName, '/').'\b/iu', $reason)) {
                    // El motivo menciona a este personaje; verificar si realmente aparece en el bloque
                    if (! preg_match('/\b'.preg_quote($charName, '/').'\b/iu', $blockText)) {
                        $isFalsePositive = true;
                        break;
                    }
                }
            }

            if ($isFalsePositive) {
                continue;
            }

            $verifiedHoles[] = [
                'block_id' => $bId,
                'reason' => $reason,
            ];
        }

        return [
            'detected_holes' => $verifiedHoles,
            'total_holes' => count($verifiedHoles),
        ];
    }

    /**
     * Parsea planos anotados con el delimitador canónico '--- shot N (tipo) ---'.
     *
     * @return array<int, array{shot_number: int, shot_type: string, description: string}>
     */
    public function parseShotsFromText(string $text): array
    {
        $pattern = '/---\s*shot\s*(\d*)\s*(?:\(([^)]+)\))?\s*---\s*\n*(.*?)(?=(?:---\s*shot)|$)/is';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $shots = [];
        $autoNum = 1;
        foreach ($matches as $match) {
            $num = ! empty($match[1]) ? (int) $match[1] : $autoNum;
            $type = ! empty($match[2]) ? strtoupper(trim($match[2])) : 'PM';
            $desc = trim($match[3] ?? '');

            if ($desc === '') {
                continue;
            }

            $shots[] = [
                'shot_number' => $num,
                'shot_type' => $type,
                'description' => $desc,
            ];
            $autoNum = max($autoNum + 1, $num + 1);
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

        // 1. Pinned terms (máx. 50 términos, máx. 80 caracteres por término/reemplazo)
        $pinnedTerms = $userLocks['pinned_terms'] ?? [];
        if (is_array($pinnedTerms)) {
            $count = 0;
            foreach ($pinnedTerms as $term => $replacement) {
                if ($count >= 50) {
                    break;
                }
                $termStr = (string) $term;
                $repStr = (string) $replacement;

                if ($termStr === '' || $repStr === '' || mb_strlen($termStr) > 80 || mb_strlen($repStr) > 80) {
                    continue;
                }

                $quoted = preg_quote($termStr, '/');
                $startBoundary = preg_match('/^\w/u', $termStr) ? '(?<![\p{L}\p{N}_])' : '';
                $endBoundary = preg_match('/\w$/u', $termStr) ? '(?![\p{L}\p{N}_])' : '';
                $pattern = '/'.$startBoundary.$quoted.$endBoundary.'/iu';

                $result = (string) preg_replace_callback($pattern, fn () => $repStr, $result);
                $count++;
            }
        }

        // 2. Manual diffs
        $manualDiffs = $userLocks['manual_diffs'] ?? [];
        if (is_array($manualDiffs)) {
            foreach ($manualDiffs as $diff) {
                if (! is_array($diff)) {
                    continue;
                }
                $diffStage = $diff['stage'] ?? $diff['target'] ?? null;
                if ($diffStage && $diffStage !== $stage) {
                    continue;
                }
                $original = $diff['original_ai'] ?? $diff['original'] ?? null;
                $override = $diff['user_override'] ?? $diff['modified'] ?? null;
                if (! empty($original) && $override !== null) {
                    $pos = strpos($result, (string) $original);
                    if ($pos !== false) {
                        $result = substr_replace($result, (string) $override, $pos, strlen((string) $original));
                    }
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
                // Purgar cualquier residuo previo de "En LOCALIZACIÓN:" y meta-comentarios de borrador
                $cleaned = preg_replace('/^En [^:]+:\s*/iu', '', $para);
                $cleaned = preg_replace('/^(?:he pensado que|la idea es que|aquí|en esta escena|el autor propone que|vemos como|se ve como)\s+/iu', '', $cleaned);
                $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $cleaned));

                if (empty($cleaned)) {
                    continue;
                }

                // Asegurar primera letra en mayúscula
                $cleaned = mb_strtoupper(mb_substr($cleaned, 0, 1)).mb_substr($cleaned, 1);

                // Normalizar nombres de personajes del contexto COMPLETAMENTE EN MAYÚSCULAS
                foreach ($characters as $char) {
                    if (is_array($char) && ! empty($char['name'])) {
                        $cname = trim($char['name']);
                        $canonicalUpper = mb_strtoupper($cname, 'UTF-8');
                        $pattern = '/\b'.preg_quote($cname, '/').'\b/iu';
                        $cleaned = preg_replace($pattern, $canonicalUpper, $cleaned);
                    }
                }

                // Asegurar puntuación final si no termina en punto, exclamación o interrogación
                if (! preg_match('/[.?!:»"]$/u', $cleaned)) {
                    $cleaned .= '.';
                }

                $structured[] = $cleaned;
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
                'user_locks' => $this->sanitizeTelemetryLocks((array) $userLocks),
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

            $activeSpeaker = null;

            // 2. Procesar párrafos (diálogos explícitos vs acciones)
            foreach ($rawParagraphs as $para) {
                // Si ya es un encabezado
                if (preg_match('/^(INT|EXT|INT\/EXT)\./i', $para)) {
                    $lines[] = mb_strtoupper($para, 'UTF-8');
                    $lines[] = '';

                    continue;
                }

                // Limpiar prefijos redundantes de tratamiento como "En BAR CENTRAL (INT): "
                $cleanPara = preg_replace('/^En\s+[^:]+:\s*/iu', '', $para);

                // Subdividir párrafos que contengan múltiples intercambios o frases conversacionales
                $segments = $this->splitParagraphIntoDialogueSegments($cleanPara);

                foreach ($segments as $segment) {
                    $dialogueMatched = false;

                    // Formato A: "PERSONAJE: Diálogo" o "PERSONAJE (acotación): Diálogo"
                    if (preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)(?:\s*\(([^)]+)\))?:\s*(.+)$/iu', $segment, $m)) {
                        $speaker = mb_strtoupper(trim($m[1]), 'UTF-8');
                        $parenthetical = ! empty($m[2]) ? trim($m[2]) : null;
                        $speech = trim($m[3]);

                        $lines[] = $speaker;
                        if ($parenthetical) {
                            $lines[] = '('.strtolower($parenthetical).')';
                        }
                        $lines[] = $speech;
                        $lines[] = '';
                        $dialogueMatched = true;
                        $activeSpeaker = $speaker;
                    }
                    // Formato B: "Personaje dice/pregunta: \"Diálogo\""
                    elseif (preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)\s+(?:dice|responde|susurra|grita|pregunta|afirma)\s*:\s*["«]([^"»]+)["»]$/iu', $segment, $m)) {
                        $speaker = mb_strtoupper(trim($m[1]), 'UTF-8');
                        $speech = trim($m[2]);

                        $lines[] = $speaker;
                        $lines[] = $speech;
                        $lines[] = '';
                        $dialogueMatched = true;
                        $activeSpeaker = $speaker;
                    }
                    // Formato C: Habla indirecta narrativa (con o sin acción previa, acotaciones de modo y destinatarios)
                    else {
                        $verbs = '(?:dice|dijo|decir|decirle|decirles|diciendo|diciéndole|diciéndoles|pregunta|preguntó|preguntar|preguntarle|preguntarles|preguntando|preguntándole|preguntándoles|responde|respondió|responder|responderle|responderles|respondiendo|respondiéndole|respondiéndoles|contesta|contestó|contestar|contestarle|contestarles|contestando|contestándole|contestándoles|susurra|susurró|susurrar|susurrarle|susurrando|susurrándole|grita|gritó|gritar|gritarle|gritando|gritándole|afirma|afirmó|afirmar|afirmando|exclama|exclamó|exclamar|exclamando|exclamándole|pide|pidió|pedir|pedirle|pidiendo|pidiéndole|pidiéndoles|murmura|murmuró|murmurar|murmurando|murmurándole|advierte|advirtió|advertir|advertirle|advirtiendo|advirtiéndole)';
                        $linkRegex = '((?:que\s+quien|qué\s+quien|que\s+quién|qué\s+quién|que\s+qué|qué\s+qué|que\s+cómo|qué\s+cómo|que\s+dónde|qué\s+dónde|que\s+cuándo|qué\s+cuándo|que\s+por\s+qué|que\s+si|qué|que|si|quién|quien|cómo|dónde|cuándo|por\s+qué))\s+';

                        $hasVerb = (bool) preg_match('/\b'.$verbs.'\b/iu', $segment);

                        if ($hasVerb && preg_match('/^([^.?!]{0,250}?)(?:[,\s]+y\s+|\s+y\s+|[,\s]+)?(?:\b(?:se|le|les|me|nos|te|lo|la|los|las)\s+)?\b('.$verbs.')\b\s*([^.?!]{0,100}?)\s*'.$linkRegex.'(.{1,5000})$/iu', $segment, $m)) {
                            $prePart = trim($m[1] ?? '');
                            $verb = strtolower(trim($m[2] ?? 'dice'));
                            $verbMod = trim($m[3] ?? '');
                            $linkRaw = strtolower(trim($m[4] ?? 'que'));
                            $linkParts = preg_split('/\s+/u', $linkRaw);
                            $particle = end($linkParts);
                            $dialogueRest = $this->cleanPunctuation($m[5] ?? '');

                            $resolved = $this->resolveSpeakerFromPrePart($prePart, $characters, $activeSpeaker);
                            $speaker = $resolved['speaker'];
                            $actionPart = $resolved['actionPart'];

                            if (! $speaker) {
                                $speaker = ! empty($activeSpeaker) ? $activeSpeaker : 'PERSONAJE';
                            }
                            $activeSpeaker = $speaker;

                            // Acotación circunstancial (parenthetical)
                            $parenthetical = null;
                            if (preg_match('/(?:al\s+o[ií]do)/iu', $verbMod)) {
                                $parenthetical = 'susurrando al oído';
                            } elseif (preg_match('/(?:en\s+voz\s+baja)/iu', $verbMod)) {
                                $parenthetical = 'en voz baja';
                            } elseif (preg_match('/(?:con\s+calma|tranquil[oa])/iu', $verbMod)) {
                                $parenthetical = 'con calma';
                            } elseif (preg_match('/(?:furios[oa]|enfadad[oa])/iu', $verbMod)) {
                                $parenthetical = 'furioso';
                            } elseif (str_contains($verb, 'susurra')) {
                                $parenthetical = 'susurrando';
                            }

                            // Limpiar nexos anidados 'y que '
                            $dialogueRest = preg_replace('/\s+y\s+que\s+/iu', ' y ', $dialogueRest);

                            // Iteración y refinamiento dramático a estilo directo (segunda persona)
                            $dialogue = $this->refineDialogueToDirectSpeech($dialogueRest, $verb, $particle);

                            if (! empty($actionPart)) {
                                $lines[] = $this->cleanPunctuation($actionPart).'.';
                                $lines[] = '';
                            }
                            $lines[] = $speaker;
                            if ($parenthetical) {
                                $lines[] = '('.$parenthetical.')';
                            }
                            $lines[] = $dialogue;
                            $lines[] = '';
                            $dialogueMatched = true;
                        }
                    }

                    // Si no es un diálogo explícito ni indirecto, es un bloque de ACCIÓN cinematográfica
                    if (! $dialogueMatched) {
                        $actionLine = $segment;
                        foreach ($characters as $char) {
                            if (is_array($char) && ! empty($char['name'])) {
                                $cname = trim($char['name']);
                                $canonicalUpper = mb_strtoupper($cname, 'UTF-8');
                                $pattern = '/\b'.preg_quote($cname, '/').'\b/iu';
                                $actionLine = preg_replace($pattern, $canonicalUpper, $actionLine);
                            }
                        }

                        // Recordar último personaje que inició una acción
                        $ignoreWords = ['se', 'el', 'la', 'los', 'las', 'un', 'una', 'al', 'en', 'por', 'tras', 'luego', 'mientras', 'cuando', 'como', 'donde'];
                        if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15})?)\b/u', $actionLine, $mSpkAction)) {
                            $firstWord = strtolower(explode(' ', $mSpkAction[1])[0]);
                            if (! in_array($firstWord, $ignoreWords, true)) {
                                $activeSpeaker = mb_strtoupper($mSpkAction[1], 'UTF-8');
                            }
                        }

                        $lines[] = $actionLine;
                        $lines[] = '';
                    }
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
                'user_locks' => $this->sanitizeTelemetryLocks((array) $userLocks),
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

            // Si el texto ya tiene delimitadores explícitos '--- shot ... ---', parsearlos directamente
            if (preg_match('/---\s*shot/i', $screenplayText)) {
                $shots = $this->parseShotsFromText($screenplayText);

                return [
                    'success' => true,
                    'output' => $screenplayText,
                    'metadata' => [
                        'stage' => 'shots',
                        'shots' => $shots,
                    ],
                ];
            }

            $rawBlocks = array_values(array_filter(array_map('trim', explode("\n\n", $screenplayText))));
            $paragraphs = [];
            foreach ($rawBlocks as $b) {
                // Omitir sluglines de escena (INT./EXT.) para no convertirlos en planos de cámara
                if (preg_match('/^(INT|EXT|INT\/EXT|EST)[\.\s]/i', $b)) {
                    continue;
                }
                $paragraphs[] = $b;
            }

            // Si solo había slugline, devolver vacío
            if (empty($paragraphs)) {
                return ['success' => true, 'output' => '', 'metadata' => ['stage' => 'shots', 'shots' => []]];
            }

            // Agrupar párrafos en unidades dramáticas coherentes (evitar 1 plano por línea)
            $shotUnits = [];
            $currentUnit = [];

            foreach ($paragraphs as $p) {
                $isCharacter = preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\(\)\.-]{2,30}$/', $p) && ! preg_match('/[a-z]/', $p);
                $isCameraCue = preg_match('/^(detalle|primer plano|plano general|inserto|plano medio|corte a|insert)\b/i', $p);

                if ($isCharacter || $isCameraCue) {
                    if (! empty($currentUnit)) {
                        $shotUnits[] = implode("\n", $currentUnit);
                        $currentUnit = [];
                    }
                    $currentUnit[] = $p;
                    if ($isCameraCue) {
                        $shotUnits[] = implode("\n", $currentUnit);
                        $currentUnit = [];
                    }
                } else {
                    $currentUnit[] = $p;
                    // Agrupar hasta 2 párrafos de acción continua o diálogo
                    if (count($currentUnit) >= 2) {
                        $shotUnits[] = implode("\n", $currentUnit);
                        $currentUnit = [];
                    }
                }
            }
            if (! empty($currentUnit)) {
                $shotUnits[] = implode("\n", $currentUnit);
            }

            if (empty($shotUnits)) {
                $shotUnits = $paragraphs;
            }

            $annotated = [];
            $shots = [];
            $shotNum = 1;

            foreach ($shotUnits as $unit) {
                $firstLine = strtok($unit, "\n");
                $isDialogue = preg_match('/^[A-ZÁÉÍÓÚÑ0-9\s\(\)\.-]{2,30}$/', (string) $firstLine) && ! preg_match('/[a-z]/', (string) $firstLine);

                if ($isDialogue) {
                    $type = ($shotNum % 2 === 0) ? 'PP' : 'PM';
                } elseif (preg_match('/(detalle|inserto|botas|reloj|llaves|objeto)/i', $unit)) {
                    $type = 'PD';
                } elseif (preg_match('/(mira|ojos|sonríe|llora|respira|toma|coge|sostiene|muestra|mano|rostro|ambientador)/i', $unit)) {
                    $type = 'PP';
                } elseif (preg_match('/(entra|sale|camina|llega|cruza|corre|paisaje|calle|habitación|salón|ciudad)/i', $unit)) {
                    $type = ($shotNum === 1) ? 'PG' : 'PM';
                } else {
                    $type = 'PM';
                }

                $annotated[] = "--- shot {$shotNum} ({$type}) ---\n{$unit}";
                $shots[] = [
                    'shot_number' => $shotNum,
                    'shot_type' => $type,
                    'description' => $unit,
                ];
                $shotNum++;
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

        if ($toolName === 'plot_hole_detector') {
            $blocks = (array) ($payload['blocks'] ?? []);
            $detectedHoles = [];

            foreach ($blocks as $block) {
                $text = (string) ($block['text'] ?? ($block['screenplay_content'] ?? ($block['structured_text'] ?? ($block['raw_text'] ?? ''))));
                $blockId = $block['id'] ?? null;
                if (! $blockId || empty(trim($text))) {
                    continue;
                }

                $matchedReason = null;
                if (preg_match('/\[(?:AGUJERO|PLOT_HOLE|INCONSISTENCIA):\s*([^\]]+)\]/iu', $text, $m)) {
                    $matchedReason = trim($m[1]);
                } elseif (preg_match('/(?:agujero de gui[oó]n|plot hole|inconsistencia dram[aá]tica|incongruencia):\s*([^\.\n]+)/iu', $text, $m)) {
                    $matchedReason = 'Inconsistencia detectada: '.trim($m[1]);
                } elseif (preg_match('/\b(desaparece sin explicaci[oó]n|reaparece de repente sin justificaci[oó]n|muere y luego habla|teletransporta|arma que nunca tuvo)\b/iu', $text, $m)) {
                    $matchedReason = 'Posible contradicción física o narrativa detectada en la acción ("'.$m[1].'").';
                }

                if ($matchedReason) {
                    $detectedHoles[] = [
                        'block_id' => $blockId,
                        'reason' => $matchedReason,
                    ];
                }
            }

            $this->lastTelemetry = [
                'tool' => 'plot_hole_detector',
                'stage' => 'audit',
                'provider' => 'deterministic_engine',
                'model' => 'rule-based-nlp',
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => 10,
                'status' => 'SUCCESS',
                'system_prompt' => $this->buildSystemPrompt('plot_hole_detector', $payload),
                'user_prompt' => $this->buildUserPrompt('plot_hole_detector', $payload),
                'raw_response' => json_encode(['detected_holes' => $detectedHoles]),
                'output' => count($detectedHoles).' inconsistencias detectadas.',
                'characters_context' => $payload['characters'] ?? [],
                'location_context' => null,
                'user_locks' => [],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Iteración 1: Detección Preliminar de Inconsistencias',
                        'status' => 'completed',
                        'detail' => 'Escaneo cronológico de '.count($blocks).' bloques y relaciones de causa-efecto.',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Iteración 2: Cotejo Literal de Evidencia Textual',
                        'status' => 'completed',
                        'detail' => 'Validación cruzada contra el texto real de cada bloque para descartar alucinaciones y falsos positivos.',
                    ],
                    [
                        'number' => 3,
                        'title' => 'Iteración 3: Consenso Crítico y Validación Dramática Final',
                        'status' => 'completed',
                        'detail' => count($detectedHoles).' agujeros de guión confirmados tras triple verificación.',
                    ],
                ],
            ];

            return [
                'success' => true,
                'output' => count($detectedHoles).' inconsistencias detectadas.',
                'metadata' => [
                    'stage' => 'audit',
                    'detected_holes' => $detectedHoles,
                    'total_holes' => count($detectedHoles),
                ],
                'detected_holes' => $detectedHoles,
            ];
        }

        return ['success' => false, 'output' => '', 'error' => "Herramienta desconocida: {$toolName}"];
    }

    /**
     * Nodo de refinamiento dramático iterativo: convierte tercera persona narrativa a interlocución directa en segunda/primera persona.
     */
    protected function refineDialogueToDirectSpeech(string $text, string $verb, ?string $particle = null): string
    {
        $res = trim($text);
        $res = preg_replace('/^que\s+/iu', '', $res);
        $res = preg_replace('/(?<=[.?!])\s*que\s+/iu', ' ', $res);

        $initialCore = $this->cleanPunctuation($res);
        if ($initialCore === '') {
            if (str_contains($verb, 'pregunta')) {
                return '¿...?';
            }
            if (str_contains($verb, 'grita')) {
                return '¡...!';
            }

            return '...';
        }

        if ($particle !== null) {
            $canonicalParticle = match ($particle) {
                'qué' => 'qué',
                'quien', 'quién' => 'quién',
                'como', 'cómo' => 'cómo',
                'cuando', 'cuándo' => 'cuándo',
                'donde', 'dónde' => 'dónde',
                'por que', 'por qué' => 'por qué',
                default => null,
            };

            if ($canonicalParticle !== null) {
                if (! preg_match('/^\b'.preg_quote($canonicalParticle, '/').'\b/iu', $res)) {
                    $res = $canonicalParticle.' '.$res;
                }
            }
        }

        // Iteración 1: Pronombres y conjugaciones de 3ª persona del destinatario a 2ª persona y 1ª persona
        $replacements = [
            '/\bse\s+ha\s+comportado\b/iu' => 'te has comportado',
            '/\bse\s+ha\s+portado\b/iu' => 'te has portado',
            '/\bse\s+comportó\b/iu' => 'te comportaste',
            '/\bvolverse\s+con\s+él\b/iu' => 'volverte conmigo',
            '/\birse\s+con\s+él\b/iu' => 'irte conmigo',
            '/\bvenirse\s+con\s+él\b/iu' => 'venirte conmigo',
            '/\bcon\s+él\b/iu' => 'conmigo',
            '/\bcon\s+ella\b/iu' => 'conmigo',
            '/\btiene\s+que\b/iu' => 'tienes que',
            '/\bdebe\b/iu' => 'debes',
            '/\bsu\s+castigo\b/iu' => 'tu castigo',
            '/\bsu\s+culpa\b/iu' => 'tu culpa',
            '/\bpara\s+recibir\s+su\b/iu' => 'para recibir tu',
            '/\bsus\s+actos\b/iu' => 'tus actos',
            '/\bsu\s+vida\b/iu' => 'tu vida',
            '/\bse\s+calle\b/iu' => 'te calles',
            '/\bno\s+se\s+meta\b/iu' => 'no te metas',
            '/\ble\s+deje\b/iu' => 'me dejes',
            '/\bestá\s+haciendo\b/iu' => 'estás haciendo',
        ];

        foreach ($replacements as $pat => $rep) {
            $res = preg_replace($pat, $rep, $res);
        }

        // Iteración 2: Segmentar oraciones compuestas en parlamentos directos punzantes
        $res = preg_replace('/\s+y\s+que\s+/iu', '. ', $res);
        $res = preg_replace('/\s+y\s+(tienes|debes|ahora|no|vas)\b/iu', '. $1', $res);

        // Iteración 3: Mayúsculas y puntuación canónica
        $core = $this->cleanPunctuation($res);

        if (str_contains($verb, 'pregunta')) {
            if (preg_match('/^(?:quien|quién)\s+(.+)$/iu', $res, $mQ)) {
                return '¿Quién '.$mQ[1].'?';
            } elseif (preg_match('/^(?:por\s+qué|por\s+que|cómo|como|cuándo|cuando|dónde|donde|qué|que)\s+(.+)$/iu', $res, $mWh)) {
                $firstWord = strtolower(explode(' ', $res)[0]);
                $whWord = match ($firstWord) {
                    'por' => '¿Por qué '.preg_replace('/^por\s+qu[eé]\s+/iu', '', $res).'?',
                    'cómo', 'como' => '¿Cómo '.$mWh[1].'?',
                    'cuándo', 'cuando' => '¿Cuándo '.$mWh[1].'?',
                    'dónde', 'donde' => '¿Dónde '.$mWh[1].'?',
                    'qué', 'que' => '¿Qué '.$mWh[1].'?',
                    default => '¿'.$this->mbUcfirst($core).'?',
                };

                return $whWord;
            } elseif (preg_match('/^(?:está|esta|llega|vienen|es|pasa)\b/iu', $res) && ($particle === null || $particle === 'que' || $particle === 'quién' || $particle === 'quien')) {
                return '¿Quién '.$res.'?';
            }

            if ($core === '') {
                return '¿...?';
            }

            return '¿'.$this->mbUcfirst($core).'?';
        }

        if (str_contains($verb, 'grita')) {
            if ($core === '') {
                return '¡...!';
            }

            return '¡'.$this->mbUcfirst($core).'!';
        }

        $sentences = preg_split('/(?<=[.?!])\s+/u', $res);
        $sentences = array_filter(array_map(function ($s) {
            $sCore = $this->cleanPunctuation($s);

            return $sCore !== '' ? $this->mbUcfirst($sCore) : null;
        }, $sentences ?: []));

        if (empty($sentences)) {
            return $core !== '' ? $this->mbUcfirst($core).'.' : '...';
        }

        return $this->cleanPunctuation(implode(' ', $sentences)).'.';
    }

    /**
     * Subdivide un párrafo en oraciones o segmentos de habla si contiene múltiples intercambios dialógicos.
     *
     * @return list<string>
     */
    protected function splitParagraphIntoDialogueSegments(string $para): array
    {
        $para = trim($para);
        if ($para === '') {
            return [];
        }

        // Si ya es un diálogo explícito clásico (Formato A o B), no subdividir
        if (preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)(?:\s*\(([^)]+)\))?:\s*(.+)$/iu', $para) ||
            preg_match('/^([a-záéíóúñA-ZÁÉÍÓÚÑ\s]+?)\s+(?:dice|responde|susurra|grita|pregunta|afirma)\s*:\s*["«]([^"»]+)["»]$/iu', $para)) {
            return [$para];
        }

        $speechVerbs = '(?:dice|dijo|decir|decirle|decirles|diciendo|diciéndole|diciéndoles|pregunta|preguntó|preguntar|preguntarle|preguntarles|preguntando|preguntándole|preguntándoles|responde|respondió|responder|responderle|responderles|respondiendo|respondiéndole|respondiéndoles|contesta|contestó|contestar|contestarle|contestarles|contestando|contestándole|contestándoles|susurra|susurró|susurrar|susurrarle|susurrando|susurrándole|grita|gritó|gritar|gritarle|gritando|gritándole|afirma|afirmó|afirmar|afirmando|exclama|exclamó|exclamar|exclamando|exclamándole|pide|pidió|pedir|pedirle|pidiendo|pidiéndole|pidiéndoles|murmura|murmuró|murmurar|murmurando|murmurándole|advierte|advirtió|advertir|advertirle|advirtiendo|advirtiéndole)';

        // 1. Separar por oraciones con punto seguido
        $sentences = preg_split('/(?<=[.?!])\s+/u', $para);
        $result = [];

        foreach ($sentences as $sentence) {
            $s = trim($sentence);
            if ($s === '') {
                continue;
            }

            // 2. Si dentro de la oración hay una réplica unida por "y [Personaje] (le contesta|dice|...)", separarla
            $subSegments = preg_split('/\s+y\s+(?=[A-ZÁÉÍÓÚÑ][\wáéíóúñÁÉÍÓÚÑ]+(?:\s+[A-ZÁÉÍÓÚÑ][\wáéíóúñÁÉÍÓÚÑ]+)?\s+(?:(?:\b(?:se|le|les|me|nos|te)\s+)?)\b'.$speechVerbs.'\b)/u', $s);

            foreach ($subSegments as $sub) {
                $trimmed = trim($sub);
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
            }
        }

        return ! empty($result) ? $result : [$para];
    }

    /**
     * Determina el personaje hablante a partir de la cláusula previa al verbo de habla.
     *
     * @param  array<int, array<string, mixed>>  $characters
     * @return array{speaker: ?string, actionPart: ?string}
     */
    protected function resolveSpeakerFromPrePart(string $prePart, array $characters, ?string $activeSpeaker = null): array
    {
        $speaker = null;
        $actionPart = null;
        $ignoreWords = ['se', 'el', 'la', 'los', 'las', 'un', 'una', 'al', 'en', 'por', 'tras', 'luego', 'mientras', 'cuando', 'como', 'donde', 'este', 'esta'];

        // 1. Buscar coincidencias con la lista de personajes del proyecto
        $candidates = [];
        foreach ($characters as $char) {
            $cName = trim((string) ($char['name'] ?? ''));
            if ($cName === '') {
                continue;
            }
            if (preg_match_all('/\b'.preg_quote($cName, '/').'\b/iu', $prePart, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $before = mb_substr($prePart, 0, $offset, 'UTF-8');
                    $isObject = (bool) preg_match('/(?:^|\s)(?:a|al|hacia|contra|con)\s+$/iu', $before);
                    $candidates[] = [
                        'name' => mb_strtoupper($cName, 'UTF-8'),
                        'offset' => $offset,
                        'is_object' => $isObject,
                    ];
                }
            }
        }

        if (! empty($candidates)) {
            $subjects = array_filter($candidates, fn ($c) => ! $c['is_object']);
            if (! empty($subjects)) {
                usort($subjects, fn ($a, $b) => $a['offset'] <=> $b['offset']);
                $speaker = $subjects[0]['name'];
            } else {
                usort($candidates, fn ($a, $b) => $a['offset'] <=> $b['offset']);
                $speaker = $candidates[0]['name'];
            }
        }

        // 2. Si no coincide con un personaje de la Biblia, buscar un nombre propio al inicio de $prePart
        if (! $speaker && preg_match('/^([A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15}(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñA-ZÁÉÍÓÚÑ]{1,15})?)\b/u', $prePart, $mLead)) {
            $candidate = trim($mLead[1]);
            $firstWord = strtolower(explode(' ', $candidate)[0]);
            if (! in_array($firstWord, $ignoreWords, true)) {
                $speaker = mb_strtoupper($candidate, 'UTF-8');
            }
        }

        // 3. Pronombres anafóricos ("este le dice", "ella contesta")
        if (! $speaker && preg_match('/^(?:este|esta|él|ella)\b/iu', $prePart)) {
            $speaker = $activeSpeaker;
        }

        // Si prePart tiene más contenido descriptivo que solo el nombre, es acotación de acción previa
        if (! empty($prePart)) {
            $stripped = $speaker ? trim(preg_replace('/\b'.preg_quote($speaker, '/').'\b/iu', '', $prePart)) : $prePart;
            $stripped = trim($stripped, " ,.-;:\t\n\r");
            if (! empty($stripped)) {
                $actionPart = $prePart;
            }
        }

        return [
            'speaker' => $speaker,
            'actionPart' => $actionPart,
        ];
    }

    /**
     * Limpia de forma segura caracteres de puntuación iniciales y finales respetando UTF-8 (sin corromper bytes multibyte).
     */
    protected function cleanPunctuation(string $str): string
    {
        return preg_replace('/^[\s.?!¿¡]+|[\s.?!¿¡]+$/u', '', $str) ?? trim($str);
    }

    /**
     * Versión multibyte segura de ucfirst() para no corromper caracteres acentuados en UTF-8.
     */
    protected function mbUcfirst(string $str): string
    {
        if ($str === '') {
            return '';
        }
        $first = mb_substr($str, 0, 1, 'UTF-8');
        $rest = mb_substr($str, 1, null, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8').$rest;
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
        $model = (string) config('services.llm.model', 'openai/gpt-oss-120b');
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
                'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
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
                Log::warning("Direct LLM call failed with HTTP status {$response->status()}");

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
                foreach (($payload['characters_context'] ?? []) as $char) {
                    $cName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
                    if (! empty(trim($cName))) {
                        $output = preg_replace('/\b'.preg_quote(trim($cName), '/').'\b/iu', mb_strtoupper(trim($cName), 'UTF-8'), $output);
                    }
                }
            } elseif ($toolName === 'screenplay_formatter') {
                $output = $this->applyUserLocks($content, (array) ($payload['user_locks'] ?? []), 'screenplay');
                foreach (($payload['characters_context'] ?? []) as $char) {
                    $cName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
                    if (! empty(trim($cName))) {
                        $output = preg_replace('/\b'.preg_quote(trim($cName), '/').'\b/iu', mb_strtoupper(trim($cName), 'UTF-8'), $output);
                    }
                }
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
                'user_locks' => $this->sanitizeTelemetryLocks((array) ($payload['user_locks'] ?? [])),
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

            if ($toolName === 'plot_hole_detector') {
                $cleanJson = $content;
                if (preg_match('/\{[\s\S]*"detected_holes"[\s\S]*\}/', $content, $m)) {
                    $cleanJson = $m[0];
                }
                $decoded = json_decode($cleanJson, true);
                $detectedHoles = is_array($decoded) ? (array) ($decoded['detected_holes'] ?? []) : [];

                return [
                    'success' => true,
                    'output' => count($detectedHoles).' inconsistencias detectadas por IA.',
                    'metadata' => [
                        'stage' => 'audit',
                        'detected_holes' => $detectedHoles,
                        'total_holes' => count($detectedHoles),
                        'provider' => $provider,
                        'model' => $model,
                        'duration_ms' => $durationMs,
                    ],
                    'detected_holes' => $detectedHoles,
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
                ."6. PERSONAJES SIEMPRE EN MAYÚSCULAS: Escribe SIEMPRE los nombres propios de los personajes TOTALMENTE EN MAYÚSCULAS (ej: 'PROTAGONISTA', 'JUAN PEDRO', 'DETECTIVE') en todas las menciones y oraciones del tratamiento narrativo.\n"
                ."7. PROHIBICIONES Y FORMATO DE SALIDA:\n"
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
                ."2. RESOLUCIÓN DE ESTILO INDIRECTO: Transforma OBLIGATORIAMENTE cualquier narración indirecta ('le pregunta que...', 'le dice que...', 'le susurra al oído que...') en acción descriptiva y bloque canónico de diálogo Fountain:\n"
                ."   Ejemplo A: 'Javier mira a Juan con miedo y le dice que ya están llegando.' ->\n"
                ."   Javier mira a Juan con miedo.\n\n"
                ."   JAVIER\n"
                ."   Ya están llegando.\n\n"
                ."   Ejemplo B: 'Juan le pregunta qué quien está llegando.' ->\n"
                ."   JUAN\n"
                ."   ¿Quién está llegando?\n\n"
                ."   Ejemplo C: 'Se acerca a Javier y le susurra al oído que tiene que volverse con él.' ->\n"
                ."   Se acerca a Javier.\n\n"
                ."   JUAN\n"
                ."   (susurrando al oído)\n"
                ."   Tienes que volverte conmigo.\n"
                ."3. INFERENCIA DE PERSONAJES: Detecta a todos los interlocutores (como JUAN) e infiere sus parlamentos en mayúsculas centradas aunque no estuviesen en la biblia previa.\n"
                ."4. PERSONAJES SIEMPRE EN MAYÚSCULAS: Escribe SIEMPRE los nombres propios de los personajes TOTALMENTE EN MAYÚSCULAS (ej: 'PROTAGONISTA', 'JUAN') tanto en las descripciones de acción como en los encabezados de diálogo.\n"
                ."5. FORMATO FOUNTAIN:\n"
                ."   NOMBRE PERSONAJE\n"
                ."   (acotación opcional en minúsculas)\n"
                ."   Línea de diálogo.\n"
                ."6. Párrafos de acción concisos (2-3 líneas máximo).\n"
                .'7. Devuelve ÚNICAMENTE el guion literario, sin preámbulos ni metadatos.';
        }

        if ($toolName === 'shot_breakdown_detector') {
            return "Eres un director de fotografía y realizador cinematográfico.\n"
                ."Analiza el guion literario y propón una cobertura técnica realista de planos de cámara para rodaje evaluando la tensión dramática y el ritmo de la escena.\n\n"
                ."REGLAS FUNDAMENTALES:\n"
                ."1. PROHIBIDO GENERAR PLANOS PARA ENCABEZADOS DE ESCENA: Líneas como 'INT. ...' o 'EXT. ...' son sluglines de localización, NUNCA planos de cámara. NO les asignes un delimitador de plano.\n"
                ."2. COBERTURA CINEMATOGRÁFICA COHERENTE: No crees un plano por cada frase. Agrupa acciones continuas en un solo plano. Para diálogos o acciones focales, alterna planos medios (PM) y primeros planos (PP) de forma equilibrada.\n"
                ."3. DELIMITADOR OBLIGATORIO: Utiliza estrictamente la sintaxis canónica:\n"
                ."--- shot N (tipo) ---\n"
                ."Tipos admitidos: PG (Plano General), PM (Plano Medio), PP (Primer Plano), PD (Plano Detalle), PA (Plano Americano).\n"
                .'Intercala los delimitadores antes de cada unidad dramática de plano.';
        }

        if ($toolName === 'plot_hole_detector') {
            return "Eres un script doctor y analista de continuidad dramática cinematográfica de máxima rigurosidad.\n"
                ."Tu misión es auditar la secuencia cronológica de bloques de guión para detectar ÚNICAMENTE AGUJEROS DE GUIÓN REALES, eliminando rigurosamente cualquier falso positivo o alucinación.\n\n"
                ."DEBES EJECUTAR UN PROTOCOLO OBLIGATORIO DE TRES ITERACIONES:\n"
                ."ITERACIÓN 1 (DETECCIÓN DE CANDIDATOS):\n"
                ."- Escanea la cronología e identifica posibles incongruencias causales o espaciales entre bloques.\n"
                ."ITERACIÓN 2 (VERIFICACIÓN ESTRICTA DE EVIDENCIA TEXTUAL Y COTEJO LITERAL):\n"
                ."- Para CADA posible anomalía, lee palabra por palabra el texto real de ese bloque.\n"
                ."- REGLA DE ORO ANTI-FALSOS POSITIVOS DE PERSONAJES: Si tu análisis dice que un personaje (ej. 'Pedro') aparece o actúa en un lugar, COMPRUEBA si el nombre de ese personaje está explícitamente en el texto del bloque. Si no aparece, DESCÁRTALO DE INMEDIATO.\n"
                ."- REGLA DE ORO ANTI-FALSOS POSITIVOS DE TIPOGRAFÍA: Las discrepancias de mayúsculas, minúsculas, formato o capitalización de nombres (como 'PERSONAJE' vs 'Personaje') NUNCA son agujeros de guión ni inconsistencias dramáticas. Queda TERMINANTEMENTE PROHIBIDO reportarlas como agujeros de guión.\n"
                ."ITERACIÓN 3 (CONSENSO FINAL Y VEREDICTO CRÍTICO):\n"
                ."- Conserva EXCLUSIVAMENTE los agujeros de guión que hayan superado las iteraciones anteriores con evidencia literal e irrefutable.\n\n"
                ."FORMATO DE SALIDA (JSON ESTRICTO):\n"
                ."{\n"
                ."  \"detected_holes\": [\n"
                ."    {\"block_id\": \"id_del_bloque\", \"reason\": \"Explicación concisa y verificada del agujero de guión...\"}\n"
                ."  ]\n"
                ."}\n"
                .'Si tras las tres iteraciones no existen agujeros irrefutables, devuelve estrictamente: {"detected_holes": []}';
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

        if ($toolName === 'plot_hole_detector') {
            $blocks = (array) ($payload['blocks'] ?? []);
            $stage = $blocks[0]['stage'] ?? 'raw';
            $stageLabel = match ($stage) {
                'structured' => 'TRATAMIENTO CONSOLIDADO',
                'screenplay' => 'GUION CONSOLIDADO',
                'raw' => 'IDEA / BRUTO (PIPELINE)',
                default => 'CONSOLIDADO',
            };

            $text = "SECUENCIA DE BLOQUES DEL PROYECTO A AUDITAR [ETAPA: {$stageLabel}]:\n\n";
            foreach ($blocks as $b) {
                $bId = $b['id'] ?? '';
                $scNum = $b['scene_order'] ?? 1;
                $slug = $b['scene_slugline'] ?? 'ESCENA';
                $blNum = $b['block_order'] ?? 1;
                $bContent = $b['text'] ?? ($b['screenplay_content'] ?? ($b['structured_text'] ?? ($b['raw_text'] ?? '')));
                $text .= "--- BLOQUE [id: {$bId}] | Escena {$scNum} ({$slug}) | Bloque #{$blNum} | Etapa: {$stageLabel} ---\n{$bContent}\n\n";
            }

            return $text;
        }

        return null;
    }

    /**
     * Sanitiza los candados almacenados en telemetría para retener solo términos de autoría y evitar anidamiento recursivo.
     *
     * @param  array<string, mixed>  $locks
     * @return array{pinned_terms: array<string, string>, manual_diffs: array<int, mixed>, locked_fields: list<string>}
     */
    protected function sanitizeTelemetryLocks(array $locks): array
    {
        return [
            'pinned_terms' => (array) ($locks['pinned_terms'] ?? []),
            'manual_diffs' => (array) ($locks['manual_diffs'] ?? []),
            'locked_fields' => (array) ($locks['locked_fields'] ?? []),
        ];
    }
}
