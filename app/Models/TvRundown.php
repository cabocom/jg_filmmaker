<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastJobStage;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TvRundown extends Model
{
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'channel_config_id',
        'channel_slug',
        'day_schedule_id',
        'broadcast_job_id',
        'filmmaker_job_id',
        'tv_video_id',
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
        'status',
        'stage',
        'duration_seconds',
        'sort_order',
        'publish_to_podcast',
        'generate_short',
        'evergreen_fallback',
        'error_message',
        'scheduled_at',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'sources_json' => 'array',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'publish_to_podcast' => 'boolean',
            'generate_short' => 'boolean',
            'evergreen_fallback' => 'boolean',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<BroadcastJob, $this> */
    public function broadcastJob(): BelongsTo
    {
        return $this->belongsTo(BroadcastJob::class);
    }

    /** @return HasOne<TvVideo, $this> */
    public function tvVideo(): HasOne
    {
        return $this->hasOne(TvVideo::class, 'rundown_id');
    }

    /** @return BelongsTo<TvChannelConfig, $this> */
    public function channelConfig(): BelongsTo
    {
        return $this->belongsTo(TvChannelConfig::class, 'channel_config_id');
    }

    /** @return BelongsTo<TvDaySchedule, $this> */
    public function daySchedule(): BelongsTo
    {
        return $this->belongsTo(TvDaySchedule::class, 'day_schedule_id');
    }

    public function isBusy(): bool
    {
        $busyStates = ['queued', 'synthesizing', 'generating', 'rendering', 'processing'];
        if (in_array((string) $this->status, $busyStates, true) || in_array((string) $this->stage, $busyStates, true)) {
            return true;
        }

        $job = $this->broadcastJob;
        if ($job === null) {
            return false;
        }

        return ! in_array($job->stage, [BroadcastJobStage::COMPLETED, BroadcastJobStage::FAILED], true);
    }
}
