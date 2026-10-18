<?php

namespace App\Services;

use App\Models\AgentRequestLog;
use App\Models\TokenPricingRate;

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
        ?array $telemetry = null
    ): AgentRequestLog {
        // 1. Extraer o estimar tokens consumidos (~1 token = 4 caracteres)
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

        // 2. Cruzar con tarifa activa en token_pricing_rates
        $rate = TokenPricingRate::where('is_active', true)->first();
        $inputCostPerMillion = $rate ? (float) $rate->input_cost_per_million : 0.590000;
        $outputCostPerMillion = $rate ? (float) $rate->output_cost_per_million : 0.790000;

        $estimatedCost = ($inputTokens * ($inputCostPerMillion / 1000000)) + ($outputTokens * ($outputCostPerMillion / 1000000));
        $estimatedCost = round($estimatedCost, 6);

        $sanitizedTelemetry = null;
        if (is_array($telemetry)) {
            $sanitizedTelemetry = $telemetry;
            if (isset($sanitizedTelemetry['user_locks']['ai_telemetry'])) {
                unset($sanitizedTelemetry['user_locks']['ai_telemetry']);
            }
        }

        // 3. Persistir log de telemetría
        return AgentRequestLog::create([
            'project_id' => $projectId,
            'script_block_id' => $scriptBlockId,
            'tool_name' => $toolName,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost_usd' => $estimatedCost,
            'meta' => $sanitizedTelemetry,
        ]);
    }
}
