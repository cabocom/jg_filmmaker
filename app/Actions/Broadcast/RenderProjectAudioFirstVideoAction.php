<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Enums\BroadcastJobStage;
use App\Models\BroadcastJob;
use App\Models\Project;
use App\Models\ScriptBlock;
use App\Models\TvRundown;
use App\Models\TvVideo;
use App\Services\Broadcast\SentenceBasedShotDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RenderProjectAudioFirstVideoAction
{
    public function __construct(
        private AutoGenerateBroadcastShotsAction $broadcastAction,
        private SentenceBasedShotDistributor $sentenceDistributor,
    ) {}

    public function execute(Project $project): BroadcastJob
    {
        $project->loadMissing(['scenes.scriptBlocks', 'scenes.shots']);

        $shots = $project->scenes
            ->sortBy('scene_order')
            ->flatMap(fn ($scene) => $scene->shots->sortBy('shot_number'))
            ->values();

        if ($shots->isEmpty()) {
            throw new InvalidArgumentException('El proyecto debe llegar al menos hasta la etapa de Shots antes de renderizar el vídeo.');
        }

        /** @var ScriptBlock|null $primaryBlock */
        $primaryBlock = $project->scenes
            ->sortBy('scene_order')
            ->flatMap(fn ($scene) => $scene->scriptBlocks->sortBy('block_order'))
            ->first();

        if ($primaryBlock === null) {
            throw new InvalidArgumentException('No se encontró un bloque de guion asociado al proyecto.');
        }

        $rundown = TvRundown::query()->where('project_id', $project->id)->with('channelConfig')->first();
        $rawText = $this->resolveSpokenText($project, $rundown);

        if ($rawText === '') {
            throw new InvalidArgumentException('El guion no contiene texto locutable para generar el audio del vídeo.');
        }

        $job = DB::transaction(function () use ($project, $primaryBlock, $shots, $rundown, $rawText): BroadcastJob {
            $channelSlug = (string) ($rundown?->channel_slug ?: 'studio');
            $profile = (string) ($rundown?->resolution_profile ?: $rundown?->channelConfig?->broadcast_profile ?: '720p30');
            $videosDir = trim((string) ($rundown?->channelConfig?->videos_dir ?: 'ai-today/videos'), '/');
            $slug = Str::slug($project->title) ?: 'project-'.$project->id;
            $outputRelPath = $rundown ? "{$videosDir}/{$slug}.mp4" : null;

            $distributed = $this->sentenceDistributor->distribute(
                $shots->all(),
                $rawText,
                $rundown?->visual_credit
            );

            $artifacts = array_filter([
                'resolution_profile' => $profile,
                'output_relative_path' => $outputRelPath,
                'tts_voice' => $rundown?->tts_voice ?: 'Kore',
                'tts_provider' => $rundown?->tts_provider ?: 'gemini',
                'visual_credit' => $distributed['visual_credit'],
                'publish_to_podcast' => $rundown ? (bool) $rundown->publish_to_podcast : false,
                'generate_short' => $rundown ? (bool) $rundown->generate_short : false,
                'shots' => $distributed['shots'],
                'shots_count' => count($distributed['shots']),
                'full_oral_text' => $distributed['full_oral_text'],
            ], static fn (mixed $v): bool => $v !== null);

            $job = BroadcastJob::create([
                'channel_id' => $channelSlug,
                'format' => (string) ($rundown?->type ?: $rundown?->format ?: 'pill'),
                'title' => (string) $project->title,
                'raw_text' => $rawText,
                'language' => (string) ($rundown?->language ?: 'es'),
                'sources' => is_array($rundown?->sources_json ?? $rundown?->sources) ? ($rundown?->sources_json ?? $rundown?->sources) : [],
                'visual_mode' => (string) ($rundown?->visual_mode ?: $project->storyboard_style ?: 'storyboard'),
                'stage' => BroadcastJobStage::SHOTS,
                'project_id' => $project->id,
                'script_block_id' => $primaryBlock->id,
                'artifacts' => $artifacts,
            ]);

            if ($rundown !== null) {
                $video = TvVideo::query()->updateOrCreate(
                    ['rundown_id' => $rundown->id],
                    [
                        'project_id' => $project->id,
                        'broadcast_job_id' => $job->id,
                        'filmmaker_job_id' => $job->id,
                        'channel_slug' => $channelSlug,
                        'type' => (string) ($rundown->type ?: $rundown->format ?: 'pill'),
                        'title' => $project->title,
                        'raw_text' => $rawText,
                        'origin' => 'rundown',
                        'relative_media_path' => $outputRelPath,
                        'resolution_profile' => $profile,
                        'stage' => 'queued',
                        'status' => 'queued',
                    ]
                );

                $rundown->update([
                    'script_text' => $rawText,
                    'broadcast_job_id' => $job->id,
                    'filmmaker_job_id' => $job->id,
                    'tv_video_id' => $video->id,
                    'stage' => 'queued',
                    'status' => 'queued',
                    'error_message' => null,
                ]);
            }

            return $job;
        });

        return $this->broadcastAction->startRendering($job);
    }

    private function resolveSpokenText(Project $project, ?TvRundown $rundown): string
    {
        $chunks = [];
        foreach ($project->scenes->sortBy('scene_order') as $scene) {
            foreach ($scene->scriptBlocks->sortBy('block_order') as $block) {
                $text = trim((string) ($block->raw_text ?: $block->structured_text ?: $block->screenplay_content ?: ''));
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        $joined = trim(implode("\n\n", $chunks));

        return $joined !== '' ? $joined : trim((string) ($rundown?->script_text ?? ''));
    }
}
