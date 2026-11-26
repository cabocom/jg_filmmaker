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
use App\Services\Tv\WikipediaRundownSynthesizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class ManageTvRundownAction
{
    /** @var list<string> */
    private const array DRAFT_FIELDS = [
        'title',
        'topic_or_url',
        'source_type',
        'source_kind',
        'format',
        'type',
        'language',
        'script_text',
        'visual_credit',
        'sources',
        'sources_json',
        'tts_provider',
        'tts_voice',
        'visual_mode',
        'resolution_profile',
        'publish_to_podcast',
        'generate_short',
        'evergreen_fallback',
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
        $channelSlug = (string) ($data['channel_slug'] ?? 'ai-today');
        $channelConfig = $this->channelConfigAction->getOrCreateConfig($channelSlug);
        $sourceKind = (string) ($data['source_kind'] ?? $data['source_type'] ?? 'manual');
        $type = (string) ($data['type'] ?? $data['format'] ?? 'pill');
        $topicOrUrl = isset($data['topic_or_url']) ? trim((string) $data['topic_or_url']) : null;
        if ($topicOrUrl === '') {
            $topicOrUrl = null;
        }
        if ($sourceKind === 'wikipedia' && $topicOrUrl !== null && preg_match('#^[a-z][a-z0-9+\-.]*://#i', $topicOrUrl) === 1) {
            app(WikipediaRundownSynthesizer::class)->validateWikipediaUrl($topicOrUrl);
        }

        $scriptText = isset($data['script_text']) && trim((string) $data['script_text']) !== ''
            ? trim((string) $data['script_text'])
            : null;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $topicOrUrl !== null ? mb_substr($topicOrUrl, 0, 120) : 'Nuevo programa TV';
        }

        $visualMode = (string) ($data['visual_mode'] ?? 'storyboard');
        $initialStage = $sourceKind === 'wikipedia' ? 'generating' : 'draft';
        $initialStatus = $sourceKind === 'wikipedia' ? 'synthesizing' : 'draft';

        $rundown = DB::transaction(function () use (
            $data,
            $owner,
            $channelSlug,
            $channelConfig,
            $sourceKind,
            $type,
            $topicOrUrl,
            $scriptText,
            $title,
            $visualMode,
            $initialStage,
            $initialStatus,
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
                'channel_slug' => $channelSlug,
                'day_schedule_id' => $data['day_schedule_id'] ?? null,
                'title' => $title,
                'topic_or_url' => $topicOrUrl,
                'source_type' => $sourceKind,
                'source_kind' => $sourceKind,
                'format' => $type,
                'type' => $type,
                'language' => (string) ($data['language'] ?? $channelConfig->language ?? 'es'),
                'script_text' => $scriptText,
                'visual_credit' => $data['visual_credit'] ?? null,
                'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : null,
                'sources_json' => is_array($data['sources'] ?? null) ? $data['sources'] : null,
                'tts_provider' => $this->resolveNullableString($data['tts_provider'] ?? null, $channelConfig->tts_provider ?: 'gemini'),
                'tts_voice' => $this->resolveNullableString($data['tts_voice'] ?? null, $channelConfig->tts_voice ?: 'Kore'),
                'visual_mode' => $visualMode,
                'resolution_profile' => (string) ($data['resolution_profile'] ?? $channelConfig->broadcast_profile ?: '720p30'),
                'status' => $initialStatus,
                'stage' => $initialStage,
                'publish_to_podcast' => (bool) ($data['publish_to_podcast'] ?? false),
                'generate_short' => (bool) ($data['generate_short'] ?? false),
                'evergreen_fallback' => (bool) ($data['evergreen_fallback'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);
        });

        if ($sourceKind === 'wikipedia') {
            SynthesizeTvRundownJob::dispatch($rundown->id)->onQueue('broadcast');
        }

        return $rundown->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(TvRundown $rundown, array $data): TvRundown
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('La escaleta tiene un proceso activo y está bloqueada hasta finalizar.', 409);
        }

        $filtered = array_intersect_key($data, array_flip(self::DRAFT_FIELDS));
        if (isset($filtered['type']) && ! isset($filtered['format'])) {
            $filtered['format'] = $filtered['type'];
        }
        if (isset($filtered['format']) && ! isset($filtered['type'])) {
            $filtered['type'] = $filtered['format'];
        }

        if ($filtered === []) {
            return $rundown;
        }

        DB::transaction(function () use ($rundown, $filtered): void {
            $rundown->update($filtered);
            $this->syncLinkedProject($rundown, $filtered);
        });

        return $rundown->refresh();
    }

    public function retry(TvRundown $rundown): TvRundown
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('La escaleta tiene un proceso activo.', 409);
        }
        if ($rundown->stage !== 'failed' && $rundown->status !== 'failed') {
            throw new InvalidArgumentException('Solo se pueden reintentar escaletas en estado fallido.');
        }

        $rundown->update(['error_message' => null]);

        if (($rundown->source_kind === 'wikipedia' || $rundown->source_type === 'wikipedia') && trim((string) $rundown->script_text) === '') {
            $rundown->update(['stage' => 'generating', 'status' => 'synthesizing']);
            SynthesizeTvRundownJob::dispatch($rundown->id)->onQueue('broadcast');

            return $rundown->refresh();
        }

        return $this->dispatchBroadcastProduction($rundown);
    }

    public function dispatchBroadcastProduction(TvRundown $rundown): TvRundown
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('Rundown is already being processed.');
        }

        $scriptText = trim((string) $rundown->script_text);
        if ($scriptText === '') {
            throw new RuntimeException('Rundown script_text cannot be empty before production.');
        }

        $channelSlug = (string) ($rundown->channel_slug ?: 'ai-today');
        $channelConfig = $rundown->channelConfig ?? $this->channelConfigAction->getOrCreateConfig($channelSlug);
        $resolutionProfile = (string) ($rundown->resolution_profile ?: $channelConfig->broadcast_profile ?: '720p30');
        $videosDir = trim((string) ($channelConfig->videos_dir ?: 'ai-today/videos'), '/');
        $slug = Str::slug($rundown->title) ?: 'rundown-'.$rundown->id;
        $outputRelPath = "{$videosDir}/{$slug}.mp4";

        $dto = new BroadcastJobRequestDTO(
            channelId: $channelSlug,
            format: (string) ($rundown->type ?: $rundown->format ?: 'pill'),
            title: (string) $rundown->title,
            rawText: $scriptText,
            language: (string) ($rundown->language ?: $channelConfig->language ?: 'es'),
            sources: is_array($rundown->sources_json ?? $rundown->sources) ? ($rundown->sources_json ?? $rundown->sources) : [],
            visualMode: (string) ($rundown->visual_mode ?: 'storyboard'),
            resolutionProfile: $resolutionProfile,
            ttsVoice: $rundown->tts_voice ?: $channelConfig->tts_voice,
            ttsProvider: $rundown->tts_provider ?: $channelConfig->tts_provider,
            visualCredit: $rundown->visual_credit,
            publishToPodcast: (bool) $rundown->publish_to_podcast,
            generateShort: (bool) $rundown->generate_short,
            outputRelativePath: $outputRelPath,
        );

        $job = $this->broadcastAction->initialize($dto);

        $video = TvVideo::query()->updateOrCreate(
            ['rundown_id' => $rundown->id],
            [
                'project_id' => $rundown->project_id ?? $job->project_id,
                'broadcast_job_id' => $job->id,
                'filmmaker_job_id' => $job->id,
                'channel_slug' => $channelSlug,
                'type' => (string) ($rundown->type ?: $rundown->format ?: 'pill'),
                'title' => $rundown->title,
                'source_ref' => $rundown->topic_or_url,
                'raw_text' => $scriptText,
                'origin' => 'rundown',
                'relative_media_path' => $outputRelPath,
                'resolution_profile' => $resolutionProfile,
                'publish_to_podcast' => (bool) $rundown->publish_to_podcast,
                'generate_short' => (bool) $rundown->generate_short,
                'evergreen_fallback' => (bool) $rundown->evergreen_fallback,
                'stage' => 'queued',
                'status' => 'queued',
            ]
        );

        $rundown->update([
            'broadcast_job_id' => $job->id,
            'filmmaker_job_id' => $job->id,
            'tv_video_id' => $video->id,
            'status' => 'queued',
            'stage' => 'queued',
            'error_message' => null,
        ]);

        return $rundown->refresh();
    }

    public function delete(TvRundown $rundown): void
    {
        if ($rundown->isBusy()) {
            throw new RuntimeException('La escaleta tiene un proceso activo y no puede eliminarse.', 409);
        }

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
