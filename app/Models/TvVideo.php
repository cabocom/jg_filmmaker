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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'rundown_id',
        'broadcast_job_id',
        'title',
        'file_path',
        'podcast_path',
        'short_path',
        'thumbnail_path',
        'resolution_profile',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'metadata',
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
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Project associated with this TV video.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Rundown that originated this TV video.
     *
     * @return BelongsTo<TvRundown, $this>
     */
    public function rundown(): BelongsTo
    {
        return $this->belongsTo(TvRundown::class, 'rundown_id');
    }

    /**
     * Broadcast job that rendered this TV video.
     *
     * @return BelongsTo<BroadcastJob, $this>
     */
    public function broadcastJob(): BelongsTo
    {
        return $this->belongsTo(BroadcastJob::class);
    }
}
