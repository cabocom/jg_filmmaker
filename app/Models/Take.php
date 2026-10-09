<?php

namespace App\Models;

use Database\Factories\TakeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Take extends Model
{
    /** @use HasFactory<TakeFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shot_id',
        'take_number',
        'file_path',
        'is_good_take',
        'editorial_notes',
        'technical_meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'take_number' => 'integer',
            'is_good_take' => 'boolean',
            'technical_meta' => 'array',
        ];
    }

    /**
     * Shot to which this take belongs.
     */
    public function shot(): BelongsTo
    {
        return $this->belongsTo(Shot::class);
    }
}
