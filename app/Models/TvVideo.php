<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvVideo extends Model
{
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'rundown_id',
        'broadcast_job_id',
        'channel_slug',
        'type',
        'title',
        'source_ref',
        'raw_text',
        'filmmaker_job_id',
        'stage',
        'status',
        'progress_text',
        'error_message',
        'file_path',
        'mp4_url',
        'mp3_url',
        'short_url',
        'podcast_path',
        'short_path',
        'thumbnail_path',
        'resolution_profile',
        'duration_seconds',
        'file_size_bytes',
        'publish_to_podcast',
        'generate_short',
        'evergreen_fallback',
        'origin',
        'relative_media_path',
        'broadcast_compliant',
        'compliance_profile',
        'compliance_reasons_json',
        'normalization_job_id',
        'metadata',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
            'publish_to_podcast' => 'boolean',
            'generate_short' => 'boolean',
            'evergreen_fallback' => 'boolean',
            'broadcast_compliant' => 'boolean',
            'compliance_reasons_json' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function isBusy(): bool
    {
        return in_array((string) $this->stage, ['queued', 'rendering', 'normalizing'], true);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<TvRundown, $this> */
    public function rundown(): BelongsTo
    {
        return $this->belongsTo(TvRundown::class, 'rundown_id');
    }

    /** @return BelongsTo<BroadcastJob, $this> */
    public function broadcastJob(): BelongsTo
    {
        return $this->belongsTo(BroadcastJob::class);
    }
}
