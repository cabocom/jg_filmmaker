<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityBreachLog extends Model
{
    use HasFactory, HasUuids, Prunable;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'user_id',
        'attempted_at',
        'reason',
    ];

    public function prunable(): Builder
    {
        return static::where('attempted_at', '<', now()->subDays(90));
    }

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
