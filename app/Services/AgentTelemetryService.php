<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgentRequestLog;
use App\Models\Project;
use App\Models\TokenPricingRate;
use Illuminate\Support\Facades\Log;

class AgentTelemetryService
{
    /**
     * Registra el consumo de tokens y coste estimado de una petición al motor de IA.
     *
     * @param  array<string, mixed>|string  $inputPayload
     * @param  array<string, mixed>|string  $outputPayload
     * @param  array<string, mixed>|null  $telemetry
     */
    public function record(
        string $projectId,
        ?string $scriptBlockId,
        string $toolName,
        array|string $inputPayload,
        array|string $outputPayload,
        ?array $telemetry = null,
        ?string $userId = null,
        bool $isByok = false
    ): ?AgentRequestLog {
        // 1. Identificar proveedor y modelo para tarificación específica
        $provider = $telemetry['provider'] ?? ($telemetry['source'] ?? null);
        $model = $telemetry['model'] ?? null;
        $isCached = ($telemetry['cached'] ?? false) === true || ($telemetry['source'] ?? null) === 'semantic_cache';
        $isByok = $isByok || (($telemetry['is_byok'] ?? false) === true);

        $isDeterministic = in_array(strtolower((string) $provider), ['deterministic_engine'], true)
            || str_contains(strtolower((string) $model), 'rule-based')
            || $isCached;

        // Si la operación se resolvió mediante motor determinista local / fallback / caché semántica, no se cobra cuota al usuario
        if ($isDeterministic) {
            $inputTokens = 0;
            $outputTokens = 0;
            $totalTokens = 0;
            $estimatedCost = 0.0;
            $rateSnapshot = [
                'rate_id' => null,
                'provider' => $provider,
                'model' => $model,
                'is_deterministic' => true,
                'is_cached' => $isCached,
                'is_byok' => $isByok,
                'input_cost_per_million' => 0.0,
                'output_cost_per_million' => 0.0,
            ];
        } else {
            // Extraer o estimar tokens consumidos (~1 token = 4 caracteres)
            $inputTokens = 0;
            $outputTokens = 0;

            if (! empty($telemetry['usage']['prompt_tokens'])) {
                $inputTokens = (int) $telemetry['usage']['prompt_tokens'];
                $outputTokens = (int) ($telemetry['usage']['completion_tokens'] ?? 0);
            } elseif (! empty($telemetry['tokens']['prompt_tokens'])) {
                $inputTokens = (int) $telemetry['tokens']['prompt_tokens'];
                $outputTokens = (int) ($telemetry['tokens']['completion_tokens'] ?? 0);
            } elseif (! empty($telemetry['input_tokens'])) {
                $inputTokens = (int) $telemetry['input_tokens'];
                $outputTokens = (int) ($telemetry['output_tokens'] ?? 0);
            }

            if ($inputTokens <= 0) {
                $inputStr = is_string($inputPayload) ? $inputPayload : (json_encode($inputPayload, JSON_UNESCAPED_UNICODE) ?: '');
                $inputTokens = max(1, (int) ceil(strlen($inputStr) / 4));
            }

            if ($outputTokens <= 0) {
                $outputStr = is_string($outputPayload) ? $outputPayload : (json_encode($outputPayload, JSON_UNESCAPED_UNICODE) ?: '');
                $outputTokens = max(1, (int) ceil(strlen($outputStr) / 4));
            }

            $totalTokens = $inputTokens + $outputTokens;

            // 2. Cruzar con tarifa específica para provider y model en token_pricing_rates
            $rate = null;
            if (! empty($provider) && ! empty($model)) {
                $rate = TokenPricingRate::where('is_active', true)
                    ->where('provider', $provider)
                    ->where('model', $model)
                    ->first();
            }

            if (! $rate && ! empty($provider)) {
                $rate = TokenPricingRate::where('is_active', true)
                    ->where('provider', $provider)
                    ->first();
            }

            if (! $rate) {
                $rate = TokenPricingRate::where('is_active', true)->first();
            }

            $inputCostPerMillion = $rate ? (float) $rate->input_cost_per_million : 0.590000;
            $outputCostPerMillion = $rate ? (float) $rate->output_cost_per_million : 0.790000;

            $estimatedCost = ($inputTokens * ($inputCostPerMillion / 1000000)) + ($outputTokens * ($outputCostPerMillion / 1000000));
            $estimatedCost = $isByok ? 0.0 : round($estimatedCost, 6);

            $rateSnapshot = [
                'rate_id' => $rate?->id,
                'provider' => $rate?->provider ?? $provider,
                'model' => $rate?->model ?? $model,
                'is_byok' => $isByok,
                'input_cost_per_million' => $inputCostPerMillion,
                'output_cost_per_million' => $outputCostPerMillion,
            ];
        }

        $sanitizedTelemetry = [];
        if (is_array($telemetry)) {
            $sanitizedTelemetry = $this->sanitizeUtf8Recursive($telemetry);
            if (isset($sanitizedTelemetry['user_locks']['ai_telemetry'])) {
                unset($sanitizedTelemetry['user_locks']['ai_telemetry']);
            }
        }
        $sanitizedTelemetry['rate_snapshot'] = $rateSnapshot;

        // 3. Resolver usuario asociado para imputación indeleble de cuota
        if (empty($userId)) {
            $userId = Project::whereKey($projectId)->value('user_id');
        }

        // 4. Persistir log de telemetría de forma resiliente
        try {
            return AgentRequestLog::create([
                'user_id' => $userId,
                'project_id' => $projectId,
                'script_block_id' => $scriptBlockId,
                'tool_name' => $toolName,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'is_byok' => $isByok,
                'estimated_cost_usd' => $estimatedCost,
                'meta' => $sanitizedTelemetry,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Fallo al persistir AgentRequestLog ({$toolName}): {$e->getMessage()}", [
                'project_id' => $projectId,
                'script_block_id' => $scriptBlockId,
                'tool_name' => $toolName,
            ]);

            return null;
        }
    }

    /**
     * Sanitiza recursivamente strings a UTF-8 válido para evitar JsonEncodingException en bases de datos relacionales.
     */
    protected function sanitizeUtf8Recursive(mixed $data): mixed
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $cleanKey = is_string($key) ? mb_convert_encoding($key, 'UTF-8', 'UTF-8') : $key;
                $sanitized[$cleanKey] = $this->sanitizeUtf8Recursive($value);
            }

            return $sanitized;
        }

        return $data;
    }
}
