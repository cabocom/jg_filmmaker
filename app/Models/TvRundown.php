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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'channel_config_id',
        'day_schedule_id',
        'broadcast_job_id',
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
        'status',
        'duration_seconds',
        'sort_order',
        'error_message',
        'scheduled_at',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Project linked 1:1 with this rundown.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Broadcast job executing this rundown.
     *
     * @return BelongsTo<BroadcastJob, $this>
     */
    public function broadcastJob(): BelongsTo
    {
        return $this->belongsTo(BroadcastJob::class);
    }

    /**
     * Rendered TV video produced from this rundown.
     *
     * @return HasOne<TvVideo, $this>
     */
    public function tvVideo(): HasOne
    {
        return $this->hasOne(TvVideo::class, 'rundown_id');
    }

    /**
     * Channel configuration associated with this rundown.
     *
     * @return BelongsTo<TvChannelConfig, $this>
     */
    public function channelConfig(): BelongsTo
    {
        return $this->belongsTo(TvChannelConfig::class, 'channel_config_id');
    }

    /**
     * Day schedule associated with this rundown.
     *
     * @return BelongsTo<TvDaySchedule, $this>
     */
    public function daySchedule(): BelongsTo
    {
        return $this->belongsTo(TvDaySchedule::class, 'day_schedule_id');
    }

    /**
     * Determine whether this rundown is actively processing.
     */
    public function isBusy(): bool
    {
        if (in_array($this->status, ['queued', 'synthesizing', 'generating', 'rendering', 'processing'], true)) {
            return true;
        }

        $job = $this->broadcastJob;
        if ($job === null) {
            return false;
        }

        return ! in_array($job->stage, [BroadcastJobStage::COMPLETED, BroadcastJobStage::FAILED], true);
    }
}
