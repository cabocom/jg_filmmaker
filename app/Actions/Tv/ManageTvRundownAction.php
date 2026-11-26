<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Actions\Broadcast\AutoGenerateBroadcastShotsAction;
use App\Actions\Projects\CreateProjectAction;
use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\DTOs\Projects\CreateProjectDTO;
use App\Enums\BlockStage;
use App\Enums\ProjectCategory;
use App\Jobs\SynthesizeTvRundownJob;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\TvRundown;
use App\Models\TvVideo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ManageTvRundownAction
{
    /**
     * @var list<string>
     */
    private const array DRAFT_FIELDS = [
        'title',
        'topic_or_url',
        'source_type',
        'format',
        'language',
        'script_text',
        'visual_credit',
        'sources',
        'tts_provider',
        'tts_voice',
        'visual_mode',
        'resolution_profile',
        'sort_order',
        'scheduled_at',
        'day_schedule_id',
    ];

    public function __construct(
        private CreateProjectAction $createProjectAction,
        private SyncTvChannelConfigAction $channelConfigAction,
        private AutoGenerateBroadcastShotsAction $broadcastAction,
    ) {}

    /**
     * Create a new TV rundown linked 1:1 to an AI_VIDEO project.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromPromptOrSource(array $data, ?User $owner = null): TvRundown
    {
        $owner ??= $this->createProjectAction->resolveSystemOwner(
            isset($data['user_id']) && is_string($data['user_id']) ? $data['user_id'] : null
        );
        $channelConfig = $this->channelConfigAction->getOrCreateConfig();
        $sourceType = (string) ($data['source_type'] ?? 'prompt');
        $topicOrUrl = isset($data['topic_or_url']) ? trim((string) $data['topic_or_url']) : null;
        $scriptText = isset($data['script_text']) ? trim((string) $data['script_text']) : null;
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            $title = ($topicOrUrl !== null && $topicOrUrl !== '')
                ? mb_substr($topicOrUrl, 0, 120)
                : 'Nuevo programa TV';
        }

        $visualMode = (string) ($data['visual_mode'] ?? 'storyboard');

        $rundown = DB::transaction(function () use (
            $data,
            $owner,
            $channelConfig,
            $sourceType,
            $topicOrUrl,
            $scriptText,
            $title,
            $visualMode,
        ): TvRundown {
            $project = $this->createProjectAction->execute($owner, new CreateProjectDTO(
                title: $title,
                status: 'draft',
                category: ProjectCategory::AI_VIDEO,
                storyboardStyle: $visualMode,
                rawText: $scriptText,
            ));

            return TvRundown::create([
                'project_id' => $project->id,
                'channel_config_id' => $data['channel_config_id'] ?? $channelConfig->id,
                'day_schedule_id' => $data['day_schedule_id'] ?? null,
                'title' => $title,
                'topic_or_url' => $topicOrUrl,
                'source_type' => $sourceType,
                'format' => (string) ($data['format'] ?? 'pill'),
                'language' => (string) ($data['language'] ?? $channelConfig->language ?? 'es'),
                'script_text' => $scriptText,
                'visual_credit' => $data['visual_credit'] ?? null,
                'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : null,
                'tts_provider' => $this->resolveNullableString($data['tts_provider'] ?? null, $channelConfig->tts_provider),
                'tts_voice' => $this->resolveNullableString($data['tts_voice'] ?? null, $channelConfig->tts_voice),
                'visual_mode' => $visualMode,
                'resolution_profile' => (string) ($data['resolution_profile'] ?? $channelConfig->resolution_profile ?? '1080p30'),
                'status' => $sourceType === 'wikipedia' ? 'synthesizing' : 'draft',
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);
        });

        if ($sourceType === 'wikipedia') {
            SynthesizeTvRundownJob::dispatch($rundown->id)->onQueue('broadcast');
        }

        return $rundown->refresh();
    }

    /**
     * Update whitelisted draft fields and sync the linked project and primary script block.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(TvRundown $rundown, array $data): TvRundown
    {
        $filtered = array_intersect_key($data, array_flip(self::DRAFT_FIELDS));

        if ($filtered === []) {
            return $rundown;
        }

        DB::transaction(function () use ($rundown, $filtered): void {
            $rundown->update($filtered);
            $this->syncLinkedProject($rundown, $filtered);
        });

        return $rundown->refresh();
    }

    /**
     * Launch broadcast production for a ready rundown and track its TvVideo.
     */
    public function dispatchBroadcastProduction(TvRundown $rundown): TvRundown
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('Rundown is already being processed.');
        }

        $scriptText = trim((string) $rundown->script_text);
        if ($scriptText === '') {
            throw new RuntimeException('Rundown script_text cannot be empty before production.');
        }

        $channelConfig = $rundown->channelConfig ?? $this->channelConfigAction->getOrCreateConfig();
        $resolutionProfile = (string) ($rundown->resolution_profile ?: $channelConfig->resolution_profile ?: '1080p30');

        $dto = new BroadcastJobRequestDTO(
            channelId: (string) ($channelConfig->slug ?: 'jg-tv'),
            format: (string) ($rundown->format ?: 'pill'),
            title: (string) $rundown->title,
            rawText: $scriptText,
            language: (string) ($rundown->language ?: $channelConfig->language ?: 'es'),
            sources: is_array($rundown->sources) ? $rundown->sources : [],
            visualMode: (string) ($rundown->visual_mode ?: 'storyboard'),
            resolutionProfile: $resolutionProfile,
            ttsVoice: $rundown->tts_voice ?: $channelConfig->tts_voice,
            ttsProvider: $rundown->tts_provider ?: $channelConfig->tts_provider,
            visualCredit: $rundown->visual_credit,
            publishToPodcast: (bool) data_get($channelConfig->metadata, 'publish_to_podcast', true),
            generateShort: (bool) data_get($channelConfig->metadata, 'generate_short', true),
        );

        $job = $this->broadcastAction->initialize($dto);

        TvVideo::query()->updateOrCreate(
            ['rundown_id' => $rundown->id],
            [
                'project_id' => $rundown->project_id ?? $job->project_id,
                'broadcast_job_id' => $job->id,
                'title' => $rundown->title,
                'resolution_profile' => $resolutionProfile,
                'status' => 'queued',
            ]
        );

        $rundown->update([
            'broadcast_job_id' => $job->id,
            'status' => 'queued',
            'error_message' => null,
        ]);

        return $rundown->refresh();
    }

    /**
     * Delete a rundown, its associated TV video, and its 1:1 linked project.
     */
    public function delete(TvRundown $rundown): void
    {
        DB::transaction(function () use ($rundown): void {
            $project = $rundown->project;
            $rundown->tvVideo()?->delete();
            $rundown->delete();

            if ($project !== null) {
                $project->delete();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $updated
     */
    private function syncLinkedProject(TvRundown $rundown, array $updated): void
    {
        $project = $rundown->project;
        if ($project === null) {
            return;
        }

        $projectUpdates = array_filter([
            'title' => array_key_exists('title', $updated) ? $rundown->title : null,
            'storyboard_style' => array_key_exists('visual_mode', $updated) ? $rundown->visual_mode : null,
        ], static fn (mixed $v): bool => $v !== null);

        if ($projectUpdates !== []) {
            $project->update($projectUpdates);
        }

        if (! array_key_exists('script_text', $updated)) {
            return;
        }

        $scriptText = trim((string) $rundown->script_text);
        $scene = $project->scenes()->orderBy('scene_order')->first();
        if ($scene === null && $scriptText !== '') {
            $scene = Scene::create([
                'project_id' => $project->id,
                'scene_order' => 1,
                'slugline' => 'INT. ESTUDIO BROADCAST - DÍA',
                'time_of_day' => 'DÍA',
            ]);
        }

        if ($scene === null) {
            return;
        }

        $block = $scene->scriptBlocks()->orderBy('block_order')->first();
        if ($block !== null) {
            $block->update(['raw_text' => $scriptText]);
        } elseif ($scriptText !== '') {
            ScriptBlock::create([
                'scene_id' => $scene->id,
                'block_order' => 1,
                'stage' => BlockStage::RAW->value,
                'raw_text' => $scriptText,
            ]);
        }
    }

    private function resolveNullableString(mixed $value, ?string $fallback): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
