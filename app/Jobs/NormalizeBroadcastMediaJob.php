<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Broadcast\NormalizeBroadcastMediaAction;
use App\Models\TvMediaNormalization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class NormalizeBroadcastMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public readonly string $normalizationId,
    ) {
        $this->onQueue('broadcast');
    }

    public function handle(NormalizeBroadcastMediaAction $action): void
    {
        $norm = TvMediaNormalization::find($this->normalizationId);

        if ($norm !== null && $norm->stage !== 'ready') {
            $action->process($norm);
        }
    }

    public function failed(Throwable $e): void
    {
        $norm = TvMediaNormalization::find($this->normalizationId);

        if ($norm !== null) {
            $norm->update([
                'stage' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }
    }
}
