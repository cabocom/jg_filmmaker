<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneTimePassword extends Model
{
    use HasFactory, HasUuids, Prunable;

    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Purga automática de OTPs caducados con más de 24 horas.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(24));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
