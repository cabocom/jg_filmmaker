<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Broadcast\AssembleBroadcastEpisodeAction;
use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AssembleBroadcastEpisodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $broadcastJobId,
    ) {
        $this->onQueue('broadcast');
    }

    public function handle(AssembleBroadcastEpisodeAction $assembleAction): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);

        if ($job === null) {
            return;
        }

        $assembleAction->execute($job);
    }

    public function failed(Throwable $exception): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);

        if ($job !== null) {
            $job->update([
                'stage' => BroadcastJobStage::FAILED,
                'failed_stage' => BroadcastJobStage::RENDERING->value,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }
    }
}
