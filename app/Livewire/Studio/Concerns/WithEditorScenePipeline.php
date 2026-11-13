<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ExecuteBlockPipelineAction;
use App\Exceptions\QuotaExceededException;
use App\Services\ScriptPipelineService;

trait WithEditorScenePipeline
{
    public function promptRunScenePipeline(string $sceneId): void
    {
        $scene = $this->projectScene($sceneId);
        $blocks = $scene->scriptBlocks()->orderBy('block_order')->get();

        if ($blocks->isEmpty()) {
            session()->flash('info', "La Escena {$scene->scene_order} no contiene bloques para estructurar.");

            return;
        }

        $totalEst = 0;
        foreach ($blocks as $block) {
            $sourceText = trim(($block->raw_text ?? '').' '.($block->structured_text ?? '').' '.($block->screenplay_content ?? ''));
            $len = max(50, mb_strlen($sourceText));
            $baseCost = (int) ceil($len / 4) + 150;

            $multiplier = 0;
            if (! $block->isStageLocked('structured') && ! empty(trim((string) $block->raw_text))) {
                $multiplier++;
            }
            if (! $block->isStageLocked('screenplay')) {
                $multiplier++;
            }
            if (! $block->isStageLocked('shots')) {
                $multiplier++;
            }
            $totalEst += max(1, $multiplier) * $baseCost;
        }

        $totalEst += 150; // Contexto biblia / overhead

        $this->scenePipelineSceneId = $scene->id;
        $this->scenePipelineSceneOrder = $scene->scene_order;
        $this->scenePipelineSceneSlugline = $scene->slugline;
        $this->estimatedSceneBlocks = $blocks->count();
        $this->estimatedSceneTokens = $totalEst;

        $user = auth()->user() ?? $this->project->user;
        if ($user && ! $user->hasDailyTokensAvailable($this->estimatedSceneTokens)) {
            $available = $user->getTokensAvailableToday();
            $limit = $user->getDailyTokenLimit();
            session()->flash('error', "Saldo insuficiente de tokens para procesar la escena ({$available} disponibles, ~{$this->estimatedSceneTokens} requeridos de tu cupo de ".number_format($limit, 0, ',', '.').' tokens). El cupo se renovará automáticamente a las 00:00.');

            return;
        }

        $this->showScenePipelineConfirmModal = true;
    }

    public function cancelRunScenePipeline(): void
    {
        $this->showScenePipelineConfirmModal = false;
        $this->scenePipelineSceneId = null;
    }

    public function confirmRunScenePipeline(ScriptPipelineService $pipelineService): void
    {
        $sceneId = $this->scenePipelineSceneId;
        $this->showScenePipelineConfirmModal = false;

        if ($sceneId) {
            $this->runScenePipeline($sceneId, $pipelineService);
        }
    }

    /**
     * Solicita confirmación de consumo de tokens para una acción de IA sobre un bloque individual.
     */
    public function runScenePipeline(string $sceneId, ScriptPipelineService $pipelineService, ?ExecuteBlockPipelineAction $action = null): void
    {
        $scene = $this->projectScene($sceneId);
        $blocks = $scene->scriptBlocks()->orderBy('block_order')->get();

        if ($blocks->isEmpty()) {
            session()->flash('info', "La Escena {$scene->scene_order} no contiene bloques para estructurar.");

            return;
        }

        try {
            $result = ($action ?? app(ExecuteBlockPipelineAction::class))->runScenePipeline($scene, $pipelineService);
        } catch (QuotaExceededException $e) {
            session()->flash('error', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            session()->flash('error', "Error al procesar el pipeline de la Escena {$scene->scene_order}: {$e->getMessage()}");

            return;
        }

        // Sincronizar Biblia del proyecto si han emergido nuevos personajes o localizaciones
        $pipelineService->syncProjectCharacters($this->project);
        $pipelineService->syncProjectLocations($this->project);

        $this->project->refresh();

        // Conmutar la escena a la vista de planos para mostrar el resultado final desglosado
        $this->scenePipelineStages[$scene->id] = 'shots';
        foreach ($blocks as $block) {
            $this->activeBlockTabs[$block->id] = 'shots';
        }

        if ($this->detailBlockId) {
            $freshDetail = $scene->scriptBlocks()->find($this->detailBlockId);
            if ($freshDetail) {
                $this->editStructuredText = (string) $freshDetail->structured_text;
                $this->editScreenplayContent = (string) $freshDetail->screenplay_content;
                $this->detailActiveTab = 'shots';
            }
        }

        $this->logActivity('run_scene_pipeline_ai', 'Scene', $scene->id, "Pipeline completo ejecutado con IA para la Escena #{$scene->scene_order} (Tratamiento ➔ Guión ➔ Planos)", [
            'scene_id' => $scene->id,
            'scene_order' => $scene->scene_order,
            'scene_slugline' => $scene->slugline,
            'blocks_count' => $blocks->count(),
            'structured_count' => $result['structured'],
            'screenplay_count' => $result['screenplay'],
            'shots_count' => $result['shots'],
            'errors' => $result['errors'],
        ]);

        if (! empty($result['errors'])) {
            session()->flash('warning', "Escena {$scene->scene_order} procesada con avisos: ".implode('; ', $result['errors']));
        } else {
            session()->flash('success', "⚡ Escena {$scene->scene_order}: {$result['structured']} tratamientos, {$result['screenplay']} guiones y {$result['shots']} bloques desglosados en planos con IA.");
        }
    }
}
