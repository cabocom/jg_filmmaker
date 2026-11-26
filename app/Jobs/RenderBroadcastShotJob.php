<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Broadcast\RenderShotClipAction;
use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\Shot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RenderBroadcastShotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $broadcastJobId,
        public readonly string $shotId,
    ) {
        $this->onQueue('broadcast');
    }

    public function handle(RenderShotClipAction $renderAction): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);
        $shot = Shot::find($this->shotId);

        if ($job === null || $shot === null) {
            return;
        }

        $context = [];
        $shotsArtifacts = $job->artifacts['shots'] ?? [];
        if (is_array($shotsArtifacts)) {
            foreach ($shotsArtifacts as $shotData) {
                if (is_array($shotData) && ($shotData['shot_id'] ?? null) === $this->shotId) {
                    $context = $shotData;
                    break;
                }
            }
        }

        $renderAction->execute($job, $shot, $context);
        $job->refresh();

        $block = $job->scriptBlock;
        if ($block === null) {
            return;
        }

        $renderedShots = is_array($job->artifacts['rendered_shots'] ?? null) ? $job->artifacts['rendered_shots'] : [];
        $renderedShotIds = array_keys($renderedShots);
        $latestShots = $job->artifacts['shots'] ?? [];
        $activeShotIds = array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_array($item) && ! empty($item['shot_id']) ? (string) $item['shot_id'] : null,
            is_array($latestShots) ? $latestShots : []
        )));

        $nextShot = $block->shots()
            ->when($activeShotIds !== [], static fn ($query) => $query->whereIn('id', $activeShotIds))
            ->whereNotIn('id', $renderedShotIds)
            ->orderBy('shot_number', 'asc')
            ->first();

        if ($nextShot !== null) {
            self::dispatch($job->id, (string) $nextShot->id)->onQueue('broadcast');
        } else {
            AssembleBroadcastEpisodeJob::dispatch($job->id)->onQueue('broadcast');
        }
    }

    public function failed(Throwable $exception): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);

        if ($job !== null) {
            $job->update([
                'stage' => BroadcastJobStage::FAILED,
                'failed_stage' => "RENDER_SHOT:{$this->shotId}",
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }
    }
}
