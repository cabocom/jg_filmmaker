<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvDaySchedule extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_config_id',
        'air_date',
        'title',
        'status',
        'slots',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'air_date' => 'date',
            'slots' => 'array',
        ];
    }

    /**
     * Channel configuration associated with this day schedule.
     *
     * @return BelongsTo<TvChannelConfig, $this>
     */
    public function channelConfig(): BelongsTo
    {
        return $this->belongsTo(TvChannelConfig::class, 'channel_config_id');
    }

    /**
     * Rundowns scheduled for this day.
     *
     * @return HasMany<TvRundown, $this>
     */
    public function rundowns(): HasMany
    {
        return $this->hasMany(TvRundown::class, 'day_schedule_id');
    }

    /**
     * Ad slots scheduled for this day.
     *
     * @return HasMany<TvAdSlot, $this>
     */
    public function adSlots(): HasMany
    {
        return $this->hasMany(TvAdSlot::class, 'day_schedule_id');
    }
}
