<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Services\ScriptPipelineService;
use Illuminate\Support\Facades\DB;

final readonly class ExecuteBlockPipelineAction
{
    /**
     * Guarda el contenido de un bloque aplicando bloqueo optimista y registrando modificaciones manuales.
     *
     * @param  array{stage: string, raw_text?: ?string, structured_text?: ?string, screenplay_content?: ?string}  $content
     */
    public function saveBlock(
        ScriptBlock $block,
        array $content,
        ?int $expectedVersion = null,
        ?ScriptPipelineService $pipelineService = null
    ): ScriptBlock|false|null {
        $pipelineService ??= app(ScriptPipelineService::class);
        $blockId = $block->id;

        return DB::transaction(function () use ($blockId, $expectedVersion, $content, $pipelineService) {
            /** @var ScriptBlock|null $fresh */
            $fresh = ScriptBlock::whereKey($blockId)->lockForUpdate()->first();
            if (! $fresh || ($expectedVersion !== null && (int) $fresh->lock_version !== (int) $expectedVersion)) {
                return $fresh ? false : null;
            }

            $editStructured = $content['structured_text'] ?? null;
            $editScreenplay = $content['screenplay_content'] ?? null;
            $this->recordOverridesIfNeeded($fresh, $pipelineService, $editStructured, $editScreenplay);

            $fresh->update([
                'raw_text' => $content['raw_text'] ?? null,
                'structured_text' => $editStructured ?: null,
                'screenplay_content' => $editScreenplay ?: null,
                'stage' => $content['stage'],
                'lock_version' => ($fresh->lock_version ?? 1) + 1,
            ]);

            return $fresh;
        });
    }

    private function recordOverridesIfNeeded(ScriptBlock $fresh, ScriptPipelineService $svc, ?string $editStruct, ?string $editScreen): void
    {
        if (! empty($fresh->structured_text) && $editStruct !== $fresh->structured_text) {
            $svc->recordManualOverride($fresh, 'structured', $fresh->structured_text, (string) $editStruct);
        }
        if (! empty($fresh->screenplay_content) && $editScreen !== $fresh->screenplay_content) {
            $svc->recordManualOverride($fresh, 'screenplay', $fresh->screenplay_content, (string) $editScreen);
        }
    }

    /**
     * Estructura un bloque bruto a tratamiento dramático.
     */
    public function structureBlock(ScriptBlock $block, ?ScriptPipelineService $pipelineService = null): void
    {
        $pipelineService ??= app(ScriptPipelineService::class);
        $pipelineService->transitionToStructured($block);
    }

    /**
     * Formatea el tratamiento a guion literario Fountain.
     */
    public function formatBlockToScreenplay(ScriptBlock $block, ?ScriptPipelineService $pipelineService = null): void
    {
        $pipelineService ??= app(ScriptPipelineService::class);
        $pipelineService->transitionToScreenplay($block);
    }

    /**
     * Desglosa el guion del bloque en planos técnicos con IA.
     */
    public function breakdownBlockShots(ScriptBlock $block, ?ScriptPipelineService $pipelineService = null): int
    {
        $pipelineService ??= app(ScriptPipelineService::class);
        $shots = $pipelineService->generateShotsBreakdown($block);

        return $shots->count();
    }

    /**
     * Alterna la protección por candado de una etapa.
     *
     * @return array{is_locked: bool, message: string, flash_type: string}
     */
    public function toggleStageLock(ScriptBlock $block, string $stage): array
    {
        $isLocked = false;
        $message = '';
        $flashType = 'info';

        $block->mutateUserLocks(function (array $userLocks) use ($stage, &$isLocked, &$message, &$flashType) {
            $lockedFields = $userLocks['locked_fields'] ?? [];

            if (in_array($stage, $lockedFields, true)) {
                if ($stage === 'structured') {
                    $lockedFields = array_values(array_filter($lockedFields, fn ($f) => ! in_array($f, ['structured', 'screenplay'], true)));
                    $message = "Etapa '{$stage}' desbloqueada (🔓). Tratamiento y guion listos para regenerar desde el bruto.";
                } else {
                    $lockedFields = array_values(array_filter($lockedFields, fn ($f) => $f !== $stage));
                    $message = "Etapa '{$stage}' desbloqueada (🔓). Ahora la IA podrá regenerarla si avanzas desde el paso anterior.";
                }
                $isLocked = false;
                $flashType = 'info';
            } else {
                $lockedFields[] = $stage;
                if ($stage === 'screenplay' && ! in_array('structured', $lockedFields, true)) {
                    $lockedFields[] = 'structured';
                }
                $message = "Etapa '{$stage}' protegida con candado (🔒). No se sobreescribirá ante regeneraciones.";
                $isLocked = true;
                $flashType = 'success';
            }

            $userLocks['locked_fields'] = array_values(array_unique($lockedFields));

            return $userLocks;
        });

        return [
            'is_locked' => $isLocked,
            'message' => $message,
            'flash_type' => $flashType,
        ];
    }

    /**
     * Ejecuta el pipeline completo sobre todos los bloques de una escena.
     *
     * @return array{structured: int, screenplay: int, shots: int, errors: array<string>}
     */
    public function runScenePipeline(Scene $scene, ?ScriptPipelineService $pipelineService = null): array
    {
        $pipelineService ??= app(ScriptPipelineService::class);
        $blocks = $scene->scriptBlocks()->orderBy('block_order')->get();

        return $pipelineService->batchRunFullPipeline($blocks);
    }
}
