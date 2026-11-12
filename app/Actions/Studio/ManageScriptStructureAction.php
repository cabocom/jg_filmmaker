<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use Illuminate\Support\Facades\DB;

final readonly class ManageScriptStructureAction
{
    /**
     * Reordena un bloque dentro de la misma escena o entre diferentes escenas del proyecto.
     */
    public function reorderBlock(Project $project, string $draggedBlockId, int $targetOrder, string $targetSceneId): Scene
    {
        $targetScene = $project->scenes()->whereKey($targetSceneId)->firstOrFail();

        DB::transaction(function () use ($draggedBlockId, $targetOrder, $targetScene, $project) {
            $dragged = ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $project->id))
                ->whereKey($draggedBlockId)
                ->firstOrFail();

            if ($dragged->scene_id !== $targetScene->id) {
                $this->moveBlockToDifferentScene($dragged, $targetScene);
            }

            $blocks = ScriptBlock::where('scene_id', $targetScene->id)->orderBy('block_order')->get();
            $remaining = $blocks->reject(fn ($b) => $b->id === $draggedBlockId)->values();
            $targetIndex = max(0, min($targetOrder - 1, $remaining->count()));

            $newBlocks = $remaining->slice(0, $targetIndex)
                ->concat([$dragged])
                ->concat($remaining->slice($targetIndex))
                ->values();

            $this->reindexBlocks($newBlocks);
        });

        return $targetScene;
    }

    private function moveBlockToDifferentScene(ScriptBlock $dragged, Scene $targetScene): void
    {
        $originSceneId = $dragged->scene_id;
        $dragged->update(['scene_id' => $targetScene->id]);
        Shot::where('script_block_id', $dragged->id)->update(['scene_id' => $targetScene->id]);

        $originBlocks = ScriptBlock::where('scene_id', $originSceneId)
            ->where('id', '!=', $dragged->id)
            ->orderBy('block_order')
            ->get();

        $this->reindexBlocks($originBlocks);
        $dragged->refresh();
    }

    private function reindexBlocks(iterable $blocks): void
    {
        foreach ($blocks as $idx => $block) {
            $order = $idx + 1;
            if ($block->block_order !== $order) {
                $block->update(['block_order' => $order]);
            }
        }
    }

    /**
     * Reordena una escena dentro del proyecto.
     */
    public function reorderScene(Project $project, string $draggedSceneId, int $targetOrder): void
    {
        DB::transaction(function () use ($project, $draggedSceneId, $targetOrder) {
            $scenes = $project->scenes()->orderBy('scene_order')->get();
            $dragged = $scenes->firstWhere('id', $draggedSceneId);
            if (! $dragged) {
                return;
            }

            $remaining = $scenes->reject(fn ($s) => $s->id === $draggedSceneId)->values();
            $targetIndex = max(0, min($targetOrder - 1, $remaining->count()));

            $newScenes = $remaining->slice(0, $targetIndex)
                ->concat([$dragged])
                ->concat($remaining->slice($targetIndex))
                ->values();

            foreach ($newScenes as $idx => $sc) {
                $order = $idx + 1;
                if ($sc->scene_order !== $order) {
                    $sc->update(['scene_order' => $order]);
                }
            }
        });
    }

    /**
     * Crea una nueva escena con un bloque inicial por defecto.
     *
     * @param  array{slugline: string, time_of_day: string, location_id?: ?string}  $data
     */
    public function createScene(Project $project, array $data, ?int $insertAfterOrder = null): Scene
    {
        return DB::transaction(function () use ($project, $data, $insertAfterOrder) {
            Project::whereKey($project->id)->lockForUpdate()->first();

            if ($insertAfterOrder !== null) {
                $project->scenes()->where('scene_order', '>', $insertAfterOrder)->increment('scene_order');
                $newOrder = $insertAfterOrder + 1;
            } else {
                $maxOrder = $project->scenes()->max('scene_order') ?? 0;
                $newOrder = $maxOrder + 1;
            }

            $newScene = $project->scenes()->create([
                'scene_order' => $newOrder,
                'slugline' => strtoupper(trim($data['slugline'])),
                'time_of_day' => strtoupper(trim($data['time_of_day'])),
                'location_id' => ! empty($data['location_id']) ? $data['location_id'] : null,
            ]);

            $newScene->scriptBlocks()->create([
                'block_order' => 1,
                'raw_text' => 'Escribe aquí la acción o idea en bruto...',
                'stage' => 'raw',
                'user_locks' => [],
            ]);

            return $newScene;
        });
    }

    /**
     * Actualiza el encabezado canónico de una escena existente.
     *
     * @param  array{slugline: string, time_of_day: string, location_id?: ?string}  $data
     */
    public function updateScene(Scene $scene, array $data): void
    {
        $scene->update([
            'slugline' => strtoupper(trim($data['slugline'])),
            'time_of_day' => strtoupper(trim($data['time_of_day'])),
            'location_id' => ! empty($data['location_id']) ? $data['location_id'] : null,
        ]);
    }

    /**
     * Elimina permanentemente un bloque y reordena los bloques restantes en la escena.
     */
    public function deleteBlock(ScriptBlock $block): void
    {
        $scene = $block->scene;
        $block->delete();

        $remainingBlocks = $scene->scriptBlocks()->orderBy('block_order')->get();
        foreach ($remainingBlocks as $idx => $rem) {
            $order = $idx + 1;
            if ($rem->block_order !== $order) {
                $rem->update(['block_order' => $order]);
            }
        }
    }

    /**
     * Elimina una escena garantizando que no contenga bloques.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteScene(Scene $scene): array
    {
        $blockCount = $scene->scriptBlocks()->count();
        if ($blockCount > 0) {
            return [
                'success' => false,
                'message' => "No se puede eliminar la Escena #{$scene->scene_order} porque todavía contiene {$blockCount} bloque(s). Elimina primero todos sus bloques.",
            ];
        }

        $project = $scene->project;
        $order = $scene->scene_order;
        $scene->delete();

        $remainingScenes = $project->scenes()->orderBy('scene_order')->get();
        foreach ($remainingScenes as $idx => $rem) {
            $expected = $idx + 1;
            if ($rem->scene_order !== $expected) {
                $rem->update(['scene_order' => $expected]);
            }
        }

        return [
            'success' => true,
            'message' => "Escena #{$order} eliminada permanentemente.",
        ];
    }
}
