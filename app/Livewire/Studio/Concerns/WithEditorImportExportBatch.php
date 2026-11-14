<?php

declare(strict_types=1);

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ExecuteBatchPipelineAction;
use App\Actions\Studio\ExportScriptAction;
use App\Services\ScriptExportService;
use App\Services\ScriptPipelineService;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait WithEditorImportExportBatch
{
    public function exportPdf(ScriptExportService $exportService, ?ExportScriptAction $action = null): StreamedResponse
    {
        Gate::authorize('view', $this->project);
        $this->logActivity('export_script_pdf', 'Project', $this->project->id, 'Exportación de guión completo a PDF');

        return ($action ?? app(ExportScriptAction::class))->exportPdf($this->project, $exportService);
    }

    public function getIsAudioCompleteProperty(TextToSpeechService $tts): bool
    {
        return $tts->isProjectAudioComplete($this->project);
    }

    public function exportMp3(TextToSpeechService $tts, ?ExportScriptAction $action = null): ?BinaryFileResponse
    {
        Gate::authorize('view', $this->project);

        if (! $tts->isProjectAudioComplete($this->project)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No puede descargarse porque faltan audios por generar, primero tienes que escucharlo.',
            ]);

            return null;
        }

        $this->logActivity('export_script_mp3', 'Project', $this->project->id, 'Exportación de guión completo a MP3');

        return ($action ?? app(ExportScriptAction::class))->exportMp3($this->project, $tts);
    }

    public function openBatchModal(): void
    {
        $this->recalculateBatchTokenEstimation();
        $this->showBatchModal = true;
    }

    public function updatedBatchScope(): void
    {
        $this->recalculateBatchTokenEstimation();
    }

    public function updatedBatchStage(): void
    {
        $this->recalculateBatchTokenEstimation();
    }

    public function recalculateBatchTokenEstimation(?ExecuteBatchPipelineAction $action = null): void
    {
        $action ??= app(ExecuteBatchPipelineAction::class);
        $this->estimatedBatchTokens = $action->estimateTokens($this->project, $this->batchScope, $this->batchStage, $this->activeSceneId);
        $this->estimatedBatchBlocks = $this->project->scenes()->count();
    }

    public function executeBatchProcessing(ScriptPipelineService $pipelineService, ?ExecuteBatchPipelineAction $action = null): void
    {
        $this->isBatchProcessing = true;
        $this->recalculateBatchTokenEstimation($action);

        $result = ($action ?? app(ExecuteBatchPipelineAction::class))->execute(
            $this->project,
            $this->batchScope,
            $this->batchStage,
            $this->activeSceneId,
            $this->estimatedBatchTokens,
            auth()->user() ?? $this->project->user,
            $pipelineService
        );

        if ($result['activity_log']) {
            $log = $result['activity_log'];
            $this->logActivity($log['action'], $log['entity_type'], $log['entity_id'], $log['description'], $log['details']);
        }

        session()->flash($result['flash_type'], $result['message']);
        $this->showBatchModal = false;
        $this->isBatchProcessing = false;
    }
}
