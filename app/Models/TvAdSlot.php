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

    /** @var list<string> */
    protected $fillable = [
        'channel_config_id',
        'day_schedule_id',
        'channel_slug',
        'asset_role',
        'name',
        'title',
        'sponsor_name',
        'media_path',
        'duration_seconds',
        'placement',
        'sort_order',
        'is_active',
        'active',
        'frequency_minutes',
        'paid_promotion',
        'stage',
        'broadcast_compliant',
        'compliance_profile',
        'compliance_reasons_json',
        'normalization_job_id',
        'error_message',
        'starts_at',
        'ends_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'frequency_minutes' => 'integer',
            'is_active' => 'boolean',
            'active' => 'boolean',
            'paid_promotion' => 'boolean',
            'broadcast_compliant' => 'boolean',
            'compliance_reasons_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isBusy(): bool
    {
        return in_array((string) $this->stage, ['queued', 'normalizing'], true);
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
}
