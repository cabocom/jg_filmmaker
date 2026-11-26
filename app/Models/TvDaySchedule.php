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

    /** @var list<string> */
    protected $fillable = [
        'channel_config_id',
        'channel_slug',
        'air_date',
        'schedule_date',
        'title',
        'status',
        'slots',
        'locked_by_admin',
        'blocks_json',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'air_date' => 'date',
            'schedule_date' => 'date',
            'slots' => 'array',
            'blocks_json' => 'array',
            'locked_by_admin' => 'boolean',
        ];
    }

    /** @return BelongsTo<TvChannelConfig, $this> */
    public function channelConfig(): BelongsTo
    {
        return $this->belongsTo(TvChannelConfig::class, 'channel_config_id');
    }

    /** @return HasMany<TvRundown, $this> */
    public function rundowns(): HasMany
    {
        return $this->hasMany(TvRundown::class, 'day_schedule_id');
    }

    /** @return HasMany<TvAdSlot, $this> */
    public function adSlots(): HasMany
    {
        return $this->hasMany(TvAdSlot::class, 'day_schedule_id');
    }
}
