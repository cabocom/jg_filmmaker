<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BlockStage;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\TvRundown;
use App\Services\Tv\WikipediaRundownSynthesizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SynthesizeTvRundownJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $rundownId,
    ) {
        $this->onQueue('broadcast');
    }

    public function handle(WikipediaRundownSynthesizer $synthesizer): void
    {
        $rundown = TvRundown::query()->find($this->rundownId);
        if ($rundown === null) {
            return;
        }

        $rundown->update([
            'status' => 'synthesizing',
            'stage' => 'generating',
            'error_message' => null,
        ]);

        $topicOrUrl = (string) ($rundown->topic_or_url ?: $rundown->title);
        $language = (string) ($rundown->language ?: 'es');
        $result = $synthesizer->synthesize($topicOrUrl, $language);

        $rundown->update([
            'title' => $result['title'],
            'script_text' => $result['script_text'],
            'visual_credit' => $result['visual_credit'],
            'sources' => $result['sources'],
            'sources_json' => $result['sources'],
            'status' => 'draft',
            'stage' => 'draft',
            'error_message' => null,
        ]);

        $this->syncLinkedProject($rundown->refresh());
    }

    public function failed(Throwable $exception): void
    {
        $rundown = TvRundown::query()->find($this->rundownId);
        if ($rundown !== null) {
            $rundown->update([
                'status' => 'failed',
                'stage' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }
    }

    private function syncLinkedProject(TvRundown $rundown): void
    {
        $project = $rundown->project;
        if ($project === null) {
            return;
        }

        $project->update(['title' => $rundown->title]);

        $scriptText = trim((string) $rundown->script_text);
        if ($scriptText === '') {
            return;
        }

        $scene = $project->scenes()->orderBy('scene_order')->first();
        if ($scene === null) {
            $scene = Scene::create([
                'project_id' => $project->id,
                'scene_order' => 1,
                'slugline' => 'INT. ESTUDIO BROADCAST - DÍA',
                'time_of_day' => 'DÍA',
            ]);
        }

        $block = $scene->scriptBlocks()->orderBy('block_order')->first();
        if ($block === null) {
            ScriptBlock::create([
                'scene_id' => $scene->id,
                'block_order' => 1,
                'stage' => BlockStage::RAW->value,
                'raw_text' => $scriptText,
            ]);

            return;
        }

        $block->update(['raw_text' => $scriptText]);
    }
}
