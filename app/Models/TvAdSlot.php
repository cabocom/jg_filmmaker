<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvAdSlot extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_config_id',
        'day_schedule_id',
        'name',
        'sponsor_name',
        'media_path',
        'duration_seconds',
        'placement',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
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
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Channel configuration associated with this ad slot.
     *
     * @return BelongsTo<TvChannelConfig, $this>
     */
    public function channelConfig(): BelongsTo
    {
        return $this->belongsTo(TvChannelConfig::class, 'channel_config_id');
    }

    /**
     * Day schedule associated with this ad slot.
     *
     * @return BelongsTo<TvDaySchedule, $this>
     */
    public function daySchedule(): BelongsTo
    {
        return $this->belongsTo(TvDaySchedule::class, 'day_schedule_id');
    }
}
