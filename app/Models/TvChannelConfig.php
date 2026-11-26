<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvChannelConfig extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'language',
        'timezone',
        'resolution_profile',
        'tts_provider',
        'tts_voice',
        'is_active',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Day schedules belonging to this channel configuration.
     *
     * @return HasMany<TvDaySchedule, $this>
     */
    public function daySchedules(): HasMany
    {
        return $this->hasMany(TvDaySchedule::class, 'channel_config_id');
    }

    /**
     * Rundowns belonging to this channel configuration.
     *
     * @return HasMany<TvRundown, $this>
     */
    public function rundowns(): HasMany
    {
        return $this->hasMany(TvRundown::class, 'channel_config_id');
    }

    /**
     * Ad slots belonging to this channel configuration.
     *
     * @return HasMany<TvAdSlot, $this>
     */
    public function adSlots(): HasMany
    {
        return $this->hasMany(TvAdSlot::class, 'channel_config_id');
    }
}
