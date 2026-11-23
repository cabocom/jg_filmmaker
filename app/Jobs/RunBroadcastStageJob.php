<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunBroadcastStageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $broadcastJobId,
        public readonly BroadcastJobStage $targetStage,
    ) {
        $this->onQueue('broadcast');
    }

    public function handle(AutoGenerateBroadcastShotsAction $action): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);

        if ($job === null) {
            return;
        }

        $action->executeStage($job, $this->targetStage);
    }

    public function failed(Throwable $exception): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);

        if ($job !== null) {
            app(AutoGenerateBroadcastShotsAction::class)->markFailed($job, $this->targetStage, $exception->getMessage());
        }
    }
}
