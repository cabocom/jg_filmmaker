<?php

declare(strict_types=1);

namespace App\Services\JgAgente;

use Illuminate\Support\Facades\Cache;

class SemanticPipelineCacheService
{
    /**
     * Herramientas de transformación determinista elegibles para almacenamiento en caché semántica.
     */
    public const CACHEABLE_TOOLS = [
        'treatment_structuring',
        'screenplay_formatter',
        'shot_breakdown_detector',
    ];

    /**
     * TTL por defecto para el resultado de transformaciones cacheadas (24 horas).
     */
    public const DEFAULT_TTL_SECONDS = 86400;

    /**
     * Campos efímeros o de autenticación a omitir en el cálculo del hash canónico.
     */
    protected const EXCLUDED_PAYLOAD_KEYS = [
        'api_key',
        'byok_api_key',
        'timestamp',
        'nonce',
        'correlation_id',
        'user_id',
        'project_id',
        'session_id',
    ];

    /**
     * Comprueba si una herramienta del pipeline es apta para caché semántica.
     */
    public function isCacheable(string $toolName): bool
    {
        return in_array($toolName, self::CACHEABLE_TOOLS, true);
    }

    /**
     * Genera un hash SHA-256 canónico determinista para la herramienta y su carga útil.
     *
     * @param  array<string, mixed>  $payload
     */
    public function computeHash(string $toolName, array $payload): string
    {
        $normalizedPayload = $this->normalizePayloadRecursive($payload);
        try {
            $canonicalJson = json_encode(
                [$toolName, $normalizedPayload],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException("Payload no serializable para {$toolName}: ".$e->getMessage(), 0, $e);
        }

        return hash('sha256', $canonicalJson);
    }

    /**
     * Obtiene el resultado cacheado para la invocación si existe.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function get(string $toolName, array $payload): ?array
    {
        if (! $this->isCacheable($toolName)) {
            return null;
        }

        $hash = $this->computeHash($toolName, $payload);
        $cached = Cache::get($this->formatCacheKey($hash));

        if (is_array($cached)) {
            $cached['source'] = 'semantic_cache';
            $cached['cached'] = true;
            $cached['degraded'] = false;

            return $cached;
        }

        return null;
    }

    /**
     * Almacena en caché el resultado de una transformación exitosa.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    public function put(string $toolName, array $payload, array $result, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        if (! $this->isCacheable($toolName)) {
            return;
        }

        if (($result['degraded'] ?? false) === true) {
            return;
        }

        $hash = $this->computeHash($toolName, $payload);
        Cache::put($this->formatCacheKey($hash), $result, $ttlSeconds);
    }

    /**
     * Invalida la entrada de caché para un payload concreto.
     *
     * @param  array<string, mixed>  $payload
     */
    public function forget(string $toolName, array $payload): bool
    {
        $hash = $this->computeHash($toolName, $payload);

        return Cache::forget($this->formatCacheKey($hash));
    }

    /**
     * Clave formateada para el almacén de caché de Laravel.
     */
    protected function formatCacheKey(string $hash): string
    {
        return "semantic_pipeline_cache:{$hash}";
    }

    /**
     * Normaliza y ordena recursivamente el array descartando claves efímeras.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayloadRecursive(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::EXCLUDED_PAYLOAD_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->normalizePayloadRecursive($value);
            } else {
                $clean[$key] = $value;
            }
        }

        ksort($clean);

        return $clean;
    }
}
