<?php

namespace App\Services\JgAgente\Fallbacks;

use App\Services\JgAgente\TelemetryRecorder;

class DeterministicFallbackEngine
{
    public function __construct(
        protected ScriptFallbackHandler $scriptFallback = new ScriptFallbackHandler,
        protected StoryboardFallbackHandler $storyboardFallback = new StoryboardFallbackHandler,
        protected ContinuityFallbackHandler $continuityFallback = new ContinuityFallbackHandler,
        protected CharacterFallbackHandler $characterFallback = new CharacterFallbackHandler,
        protected DirectLlmFallbackHandler $directLlmFallback = new DirectLlmFallbackHandler
    ) {}

    /**
     * Enruta y ejecuta el fallback determinista correspondiente para la herramienta solicitada.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function executeFallback(
        string $toolName,
        array $payload,
        TelemetryRecorder $telemetry,
        callable $userLocksApplier,
        callable $shotParser
    ): array {
        // Fallback opcional a LLM directo si está explícitamente configurado (no en prod/test)
        $allowDirectLlm = ! app()->isProduction()
            && ! app()->environment('testing')
            && (bool) config('services.llm.direct_enabled', false);

        if ($allowDirectLlm) {
            $directResult = $this->directLlmFallback->callDirectLlm(
                $toolName,
                $payload,
                $telemetry,
                $userLocksApplier,
                $shotParser
            );
            if ($directResult !== null) {
                $directResult['source'] = 'direct_llm';
                $directResult['degraded'] = true;

                return $directResult;
            }
        }

        return match ($toolName) {
            'treatment_structuring' => $this->scriptFallback->handleTreatmentStructuring($payload, $telemetry, $userLocksApplier),
            'screenplay_formatter' => $this->scriptFallback->handleScreenplayFormatting($payload, $telemetry, $userLocksApplier),
            'shot_breakdown_detector' => $this->storyboardFallback->handleShotBreakdown($payload, $telemetry, $shotParser),
            'generate_storyboard' => $this->storyboardFallback->handleStoryboardGeneration($payload),
            'plot_hole_detector' => $this->continuityFallback->handlePlotHolesAudit($payload, $telemetry),
            'extract_characters' => $this->characterFallback->handleCharacterExtraction($payload),
            'audit_character_usage' => $this->characterFallback->handleCharacterAudit($payload),
            default => ['success' => false, 'output' => '', 'error' => "Herramienta desconocida: {$toolName}"],
        };
    }
}
