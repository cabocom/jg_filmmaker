<?php

namespace App\Services\JgAgente\Fallbacks;

use App\Services\JgAgente\TelemetryRecorder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectLlmFallbackHandler
{
    /**
     * Inferencia semántica directa con proveedor LLM configurado (Groq / OpenAI / Gemini).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function callDirectLlm(
        string $toolName,
        array $payload,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier,
        callable $shotParser
    ): ?array {
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

            return $this->formatDirectLlmResponse(
                $toolName,
                $content,
                $payload,
                $provider,
                $model,
                $durationMs,
                $systemPrompt,
                $userPrompt,
                $telemetry,
                $userLocksApplier,
                $shotParser
            );
        } catch (\Throwable $e) {
            Log::warning("Direct LLM exception: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Formatea la respuesta del LLM y registra telemetría.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function formatDirectLlmResponse(
        string $toolName,
        string $content,
        array $payload,
        string $provider,
        string $model,
        int $durationMs,
        string $systemPrompt,
        string $userPrompt,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier,
        callable $shotParser
    ): ?array {
        $stage = match ($toolName) {
            'treatment_structuring' => 'structured',
            'screenplay_formatter' => 'screenplay',
            'shot_breakdown_detector' => 'shots',
            default => 'raw',
        };

        $output = $content;

        if ($toolName === 'treatment_structuring') {
            $clean = preg_replace('/^En [^:]+:\s*/i', '', $content);
            $output = $userLocksApplier((string) $clean, (array) ($payload['user_locks'] ?? []), 'structured');
            foreach (($payload['characters_context'] ?? []) as $char) {
                $cName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
                if (! empty(trim($cName))) {
                    $output = preg_replace('/\b'.preg_quote(trim($cName), '/').'\b/iu', mb_strtoupper(trim($cName), 'UTF-8'), $output);
                }
            }
        } elseif ($toolName === 'screenplay_formatter') {
            $output = $userLocksApplier($content, (array) ($payload['user_locks'] ?? []), 'screenplay');
            foreach (($payload['characters_context'] ?? []) as $char) {
                $cName = is_array($char) ? ($char['name'] ?? '') : (string) $char;
                if (! empty(trim($cName))) {
                    $output = preg_replace('/\b'.preg_quote(trim($cName), '/').'\b/iu', mb_strtoupper(trim($cName), 'UTF-8'), $output);
                }
            }
        }

        $telemetry->setLastTelemetry([
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
            'user_locks' => $telemetry->sanitizeTelemetryLocks((array) ($payload['user_locks'] ?? [])),
            'steps' => [
                ['number' => 1, 'title' => 'Inyección Contextual & Biblia Dramática', 'status' => 'completed', 'detail' => 'Cargados '.count($payload['characters_context'] ?? []).' personajes.'],
                ['number' => 2, 'title' => 'Inferencia Cognitiva LLM ('.$model.')', 'status' => 'completed', 'detail' => "Inferencia resuelta en {$provider} en {$durationMs} ms."],
                ['number' => 3, 'title' => 'Aplicación de Candados y Términos Fijos', 'status' => 'completed', 'detail' => 'Verificados y protegidos los términos fijados.'],
                ['number' => 4, 'title' => 'Normalización de Salida', 'status' => 'completed', 'detail' => 'Salida normalizada.'],
            ],
        ]);

        if ($toolName === 'treatment_structuring') {
            return ['success' => true, 'output' => $output, 'metadata' => ['stage' => 'structured', 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs]];
        }
        if ($toolName === 'screenplay_formatter') {
            return ['success' => true, 'output' => $output, 'metadata' => ['stage' => 'screenplay', 'format' => 'fountain', 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs]];
        }
        if ($toolName === 'shot_breakdown_detector') {
            $shots = $shotParser($content);

            return ['success' => true, 'output' => $content, 'metadata' => ['stage' => 'shots', 'shots' => $shots, 'total_shots' => count($shots), 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs]];
        }
        if ($toolName === 'plot_hole_detector') {
            $cleanJson = $content;
            if (preg_match('/\{[\s\S]*"detected_holes"[\s\S]*\}/', $content, $m)) {
                $cleanJson = $m[0];
            }
            $decoded = json_decode($cleanJson, true);
            $detectedHoles = is_array($decoded) ? (array) ($decoded['detected_holes'] ?? []) : [];

            return ['success' => true, 'output' => count($detectedHoles).' inconsistencias detectadas por IA.', 'metadata' => ['stage' => 'audit', 'detected_holes' => $detectedHoles, 'total_holes' => count($detectedHoles), 'provider' => $provider, 'model' => $model, 'duration_ms' => $durationMs], 'detected_holes' => $detectedHoles];
        }

        return null;
    }

    /**
     * Construye el system prompt cinematográfico profesional según la herramienta.
     *
     * @param  array<string, mixed>  $payload
     */
    public function buildSystemPrompt(string $toolName, array $payload): ?string
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
            $locDesc = "{$location['name']} (".($location['type'] ?? 'INT').')';
        }

        $userLocks = (array) ($payload['user_locks'] ?? []);
        $pinned = (array) ($userLocks['pinned_terms'] ?? []);
        $locksDesc = empty($pinned) ? 'Ningún término fijado.' : implode("\n", array_map(fn ($k, $v) => "- Sustituir '{$k}' siempre por '{$v}'", array_keys($pinned), $pinned));

        if ($toolName === 'treatment_structuring') {
            return "Eres un consultor y analista cinematográfico profesional.\nTransforma notas en un TRATAMIENTO CINEMATOGRÁFICO NARRATIVO en presente continuo.\nPersonajes:\n{$charsDesc}\nLocalización:\n{$locDesc}\nCandados:\n{$locksDesc}\nDevuelve ÚNICAMENTE la prosa narrativa.";
        }

        if ($toolName === 'screenplay_formatter') {
            $locSlug = is_array($location) && ! empty($location['name']) ? strtoupper($location['name']) : 'LOCALIZACION';
            $locType = is_array($location) && ! empty($location['type']) ? strtoupper($location['type']) : 'INT';
            $timeOfDay = is_array($location) && ! empty($location['time_of_day']) ? strtoupper($location['time_of_day']) : 'DIA';
            $slugline = "{$locType}. {$locSlug} - {$timeOfDay} 1";

            return "Eres un guionista cinematográfico profesional.\nTransforma el tratamiento en un GUION FOUNTAIN estándar iniciando con {$slugline}.\nDevuelve ÚNICAMENTE el guion literario.";
        }

        if ($toolName === 'shot_breakdown_detector') {
            return "Eres un realizador cinematográfico.\nPropón cobertura técnica de planos delimitando con '--- shot (tipo) ---'.\nTipos: PG, PM, PP, PD, PA.";
        }

        if ($toolName === 'plot_hole_detector') {
            return "Eres un script doctor de continuidad dramática.\nAudita la secuencia cronológica de bloques para detectar ÚNICAMENTE AGUJEROS DE GUIÓN REALES.\nSalida en JSON: {\"detected_holes\": [{\"block_id\": \"...\", \"reason\": \"...\"}]}";
        }

        return null;
    }

    /**
     * Construye el user prompt con los datos del autor.
     *
     * @param  array<string, mixed>  $payload
     */
    public function buildUserPrompt(string $toolName, array $payload): ?string
    {
        if ($toolName === 'treatment_structuring') {
            return 'NOTAS / TEXTO BRUTO DEL AUTOR:\n'.trim((string) ($payload['raw_text'] ?? ''));
        }
        if ($toolName === 'screenplay_formatter') {
            return 'TRATAMIENTO DRAMÁTICO:\n'.trim((string) ($payload['structured_text'] ?? ''));
        }
        if ($toolName === 'shot_breakdown_detector') {
            return 'GUIÓN LITERARIO:\n'.trim((string) ($payload['screenplay_text'] ?? ''));
        }
        if ($toolName === 'plot_hole_detector') {
            $blocks = (array) ($payload['blocks'] ?? []);
            $text = "SECUENCIA DE BLOQUES DEL PROYECTO A AUDITAR:\n\n";
            foreach ($blocks as $b) {
                $bId = $b['id'] ?? '';
                $bContent = $b['text'] ?? ($b['screenplay_content'] ?? ($b['structured_text'] ?? ($b['raw_text'] ?? '')));
                $text .= "--- BLOQUE [id: {$bId}] ---\n{$bContent}\n\n";
            }

            return $text;
        }

        return null;
    }
}
