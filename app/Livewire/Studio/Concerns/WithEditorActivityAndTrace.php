<?php

namespace App\Livewire\Studio\Concerns;

use App\Models\AgentRequestLog;
use App\Models\ScriptBlock;
use App\Models\UserActivityLog;
use App\Services\Studio\StudioAiTraceBuilder;

trait WithEditorActivityAndTrace
{
    protected function logActivity(string $action, string $entityType, ?string $entityId, string $description, ?array $snapshot = null): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        // Deduplicación de snapshots para evitar inflar SQLite con textos idénticos (ISSUE-040)
        if ($snapshot !== null && $entityId) {
            $lastLog = UserActivityLog::query()
                ->where('project_id', $this->project->id)
                ->where('entity_id', $entityId)
                ->where('action', $action)
                ->latest()
                ->first();

            if ($lastLog && $lastLog->snapshot === $snapshot) {
                $snapshot = null;
            }
        }

        UserActivityLog::create([
            'user_id' => $userId,
            'project_id' => $this->project->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Abre el modal de historial y auditoría cronológica inversa del proyecto.
     */
    public function openActivityHistoryModal(): void
    {
        $this->showActivityHistoryModal = true;
    }

    /**
     * Cierra el modal de historial de actividad.
     */
    public function closeActivityHistoryModal(): void
    {
        $this->showActivityHistoryModal = false;
    }

    /**
     * Calcula la estimación de tokens que consumirá la auditoría de agujeros y abre el modal de confirmación.
     */
    public function openAiTrace(string $blockId, string $stage): void
    {
        $block = $this->projectBlock($blockId, ['scene.project.characters', 'scene.location', 'shots']);

        $toolName = match ($stage) {
            'structured' => 'treatment_structuring',
            'screenplay' => 'screenplay_formatter',
            'shots' => 'shot_breakdown_detector',
            default => 'treatment_structuring',
        };

        $log = AgentRequestLog::where('script_block_id', $block->id)
            ->where('tool_name', $toolName)
            ->latest()
            ->first();

        $telemetry = is_array($log?->meta) ? $log->meta : null;

        if (! $telemetry) {
            $telemetry = app(StudioAiTraceBuilder::class)->generateFallbackTelemetry($block, $stage);
        }

        // Sincronizar dinámicamente con el proveedor y modelo activo real del entorno
        $activeProvider = (string) config('services.llm.provider', env('LLM_PROVIDER', 'groq'));
        $activeModel = (string) config('services.llm.model', env('LLM_MODEL', 'llama-3.3-70b-versatile'));
        if ($activeModel === 'openai/gpt-oss-120b' || empty($activeModel)) {
            $activeModel = 'llama-3.3-70b-versatile';
        }
        $telemetry['provider'] = $activeProvider;
        $telemetry['model'] = $activeModel;

        $telemetry['block_order'] = $block->block_order;
        $telemetry['scene_order'] = $block->scene?->scene_order ?? 1;
        $telemetry['scene_slugline'] = $block->scene?->slugline ?? 'ESCENA';

        // Generar pasos granulares con acciones particulares exactas solicitadas por el usuario
        $telemetry['steps'] = $this->buildGranularSteps($block, $stage, $telemetry);

        $this->aiTraceData = $telemetry;
        $this->aiTraceActiveTab = 'steps';
        $this->showAiTraceModal = true;
    }

    public function closeAiTraceModal(): void
    {
        $this->showAiTraceModal = false;
        $this->aiTraceData = [];
    }

    public function switchAiTraceTab(string $tab): void
    {
        $this->aiTraceActiveTab = $tab;
    }

    /**
     * Construye la lista de saltos y grafos con las acciones particulares exactas (personajes, localizaciones, términos).
     *
     * @param  array<string, mixed>  $telemetry
     * @return list<array<string, mixed>>
     */
    public function buildGranularSteps(ScriptBlock $block, string $stage, array $telemetry): array
    {
        return app(StudioAiTraceBuilder::class)->buildGranularSteps($block, $stage, $telemetry);
    }

    public function generateFallbackTelemetry(ScriptBlock $block, string $stage): array
    {
        return app(StudioAiTraceBuilder::class)->generateFallbackTelemetry($block, $stage);
    }
}
