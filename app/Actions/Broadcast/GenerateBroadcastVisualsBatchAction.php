<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Contracts\Broadcast\ShotVisualStrategyInterface;
use App\Models\BroadcastJob;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Services\Broadcast\Visuals\ShotVisualStrategyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class GenerateBroadcastVisualsBatchAction
{
    public function __construct(
        protected ShotVisualStrategyResolver $visualResolver
    ) {}

    /**
     * Reutiliza visuales aprobados de Studio y genera los faltantes empezando por el plano #1.
     *
     * @return array<string, string>
     */
    public function execute(BroadcastJob $job): array
    {
        $artifacts = is_array($job->artifacts) ? $job->artifacts : [];
        $shotContexts = $this->indexShotContexts($artifacts);
        $shots = $this->loadOrderedShots($job, $shotContexts);

        if ($shots->isEmpty()) {
            return [];
        }

        $strategy = $this->visualResolver->resolve((string) $job->visual_mode);
        $shotVisuals = is_array($artifacts['shot_visuals'] ?? null) ? $artifacts['shot_visuals'] : [];

        // Procesar siempre el plano #1 primero para asegurar su disponibilidad como referencia de estilo.
        /** @var Shot $anchorShot */
        $anchorShot = $shots->shift();
        $shotVisuals[(string) $anchorShot->id] = $this->resolveOrGenerateVisual(
            $job,
            $anchorShot,
            $strategy,
            $shotContexts[(string) $anchorShot->id] ?? []
        );

        foreach ($shots as $shot) {
            $shotVisuals[(string) $shot->id] = $this->resolveOrGenerateVisual(
                $job,
                $shot,
                $strategy,
                $shotContexts[(string) $shot->id] ?? []
            );
        }

        $artifacts['shot_visuals'] = $shotVisuals;
        $job->update(['artifacts' => $artifacts]);

        return $shotVisuals;
    }

    /**
     * @param  array<string, mixed>  $artifacts
     * @return array<string, array<string, mixed>>
     */
    private function indexShotContexts(array $artifacts): array
    {
        $rawShots = is_array($artifacts['shots'] ?? null) ? $artifacts['shots'] : [];
        $indexed = [];

        foreach ($rawShots as $item) {
            if (! is_array($item) || empty($item['shot_id'])) {
                continue;
            }
            $indexed[(string) $item['shot_id']] = $item;
        }

        return $indexed;
    }

    /**
     * @param  array<string, array<string, mixed>>  $shotContexts
     * @return Collection<int, Shot>
     */
    private function loadOrderedShots(BroadcastJob $job, array $shotContexts): Collection
    {
        /** @var ScriptBlock|null $block */
        $block = $job->scriptBlock()->first();
        if ($block === null) {
            return collect();
        }

        $activeIds = array_keys($shotContexts);

        return $block->shots()
            ->with('scene.project')
            ->when($activeIds !== [], static fn ($query) => $query->whereIn('id', $activeIds))
            ->orderBy('shot_number', 'asc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveOrGenerateVisual(
        BroadcastJob $job,
        Shot $shot,
        ShotVisualStrategyInterface $strategy,
        array $context
    ): string {
        $existingPath = $this->resolveExistingStoryboardPath($shot->storyboard_image_path);
        if ($existingPath !== null) {
            return $existingPath;
        }

        return $strategy->generateVisual($job, $shot, $context);
    }

    private function resolveExistingStoryboardPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return file_exists($path) ? $path : null;
    }
}
