<?php

declare(strict_types=1);

namespace App\Services\JgAgente;

use App\Services\JgAgente\Fallbacks\DeterministicFallbackEngine;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentHttpTransport
{
    protected string $baseUrl;

    protected string $secret;

    protected bool $mock;

    protected int $timeout;

    protected SemanticPipelineCacheService $cacheService;

    public function __construct(
        ?string $baseUrl = null,
        ?string $secret = null,
        ?bool $mock = null,
        ?int $timeout = null,
        ?SemanticPipelineCacheService $cacheService = null
    ) {
        $this->cacheService = $cacheService ?? new SemanticPipelineCacheService;
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.jg_agente.url', 'http://127.0.0.1:8001'), '/');
        $this->mock = $mock ?? (bool) config('services.jg_agente.mock', false);
        $this->timeout = $timeout ?? (int) config('services.jg_agente.timeout', 30);

        $resolvedSecret = $secret ?? (string) config('services.jg_agente.secret', '');
        if ($resolvedSecret === '' && ! $this->mock) {
            throw new \RuntimeException(
                'Seguridad (CWE-798): secreto HMAC de jg_agente no configurado. '
                .'Defina JG_AGENTE_SECRET en .env (o active JG_AGENTE_MOCK=true para modo offline).'
            );
        }
        $this->secret = $resolvedSecret;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function isMock(): bool
    {
        return $this->mock;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Invoca una herramienta en jg_agente o recurre a la emulación local determinista.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTool(
        string $toolName,
        array $payload,
        HmacSigner $signer,
        DeterministicFallbackEngine $fallbackEngine,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier,
        callable $shotParser
    ): array {
        if ($this->cacheService->isCacheable($toolName)) {
            $cached = $this->cacheService->get($toolName, $payload);
            if ($cached !== null) {
                $telemetry->record($toolName, $payload, $cached, 0);

                return $cached;
            }
        }

        if ($this->mock) {
            return $this->resolveFallback($toolName, $payload, $fallbackEngine, $telemetry, $userLocksApplier, $shotParser);
        }

        $path = "/tools/{$toolName}/execute";
        $url = "{$this->baseUrl}{$path}";

        try {
            $rawBody = json_encode([
                'arguments' => $payload,
                'bypass_approval' => true,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            Log::error("Error de serialización JSON en payload para jg_agente [{$toolName}]", ['error' => $e->getMessage()]);
            throw new \InvalidArgumentException("Payload no serializable para {$toolName}: ".$e->getMessage(), 0, $e);
        }

        $headers = $signer->generateSignatureHeaders('POST', $path, $rawBody, $this->secret);
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
                    if (isset($data['output']) && is_array($data['output'])) {
                        $data = array_merge($data['output'], $data);
                    }
                    $data['source'] = 'agent';
                    $data['degraded'] = false;

                    if ($this->cacheService->isCacheable($toolName)) {
                        $this->cacheService->put($toolName, $payload, $data);
                    }

                    return $data;
                }

                $error = is_array($data) ? ($data['error'] ?? 'flag success es false') : 'formato no json';
                Log::warning("Respuesta no exitosa de jg_agente para herramienta [{$toolName}]", [
                    'tool' => $toolName,
                    'correlation_id' => $correlationId,
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => "Respuesta no exitosa de jg_agente [{$toolName}]: {$error}",
                    'source' => 'agent',
                    'degraded' => false,
                ];
            }

            Log::warning("Llamada HTTP fallida a jg_agente para herramienta [{$toolName}]", [
                'tool' => $toolName,
                'correlation_id' => $correlationId,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => "Error HTTP {$response->status()} en jg_agente para herramienta [{$toolName}]",
                'source' => 'agent',
                'degraded' => false,
            ];
        } catch (ConnectionException $e) {
            Log::warning("Sin conexión con microservicio jg_agente para herramienta [{$toolName}]", [
                'tool' => $toolName,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => "Sin conexión con microservicio jg_agente para herramienta [{$toolName}]: {$e->getMessage()}",
                'source' => 'agent',
                'degraded' => false,
            ];
        } catch (Exception $e) {
            Log::warning("Excepción durante la invocación a jg_agente para herramienta [{$toolName}]", [
                'tool' => $toolName,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => "Error durante invocación a jg_agente para herramienta [{$toolName}]: {$e->getMessage()}",
                'source' => 'agent',
                'degraded' => false,
            ];
        }
    }

    /**
     * Resuelve el fallback determinista y marca metadatos de degradación.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function resolveFallback(
        string $toolName,
        array $payload,
        DeterministicFallbackEngine $fallbackEngine,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier,
        callable $shotParser
    ): array {
        $fallback = $fallbackEngine->executeFallback($toolName, $payload, $telemetry, $userLocksApplier, $shotParser);
        if (! isset($fallback['source'])) {
            $fallback['source'] = 'deterministic_fallback';
            $fallback['degraded'] = true;
        }

        return $fallback;
    }
}
