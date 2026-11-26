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

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'channel_slug',
        'tagline',
        'language',
        'timezone',
        'resolution_profile',
        'broadcast_profile',
        'tts_provider',
        'tts_voice',
        'is_active',
        'metadata',
        'youtube_channel_id',
        'youtube_oauth_encrypted',
        'youtube_stream_key_encrypted',
        'stream_key_fingerprint',
        'youtube_connected',
        'voice_profiles_json',
        'videos_dir',
        'ads_dir',
        'last_scanned_at',
        'planning_in_progress',
    ];

    /** @var list<string> */
    protected $hidden = [
        'youtube_oauth_encrypted',
        'youtube_stream_key_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'youtube_connected' => 'boolean',
            'planning_in_progress' => 'boolean',
            'metadata' => 'array',
            'voice_profiles_json' => 'array',
            'last_scanned_at' => 'datetime',
        ];
    }

    /** @return HasMany<TvDaySchedule, $this> */
    public function daySchedules(): HasMany
    {
        return $this->hasMany(TvDaySchedule::class, 'channel_config_id');
    }

    /** @return HasMany<TvRundown, $this> */
    public function rundowns(): HasMany
    {
        return $this->hasMany(TvRundown::class, 'channel_config_id');
    }

    /** @return HasMany<TvAdSlot, $this> */
    public function adSlots(): HasMany
    {
        return $this->hasMany(TvAdSlot::class, 'channel_config_id');
    }
}
