<?php

namespace App\Models;

use Database\Factories\ScriptBlockFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScriptBlock extends Model
{
    /** @use HasFactory<ScriptBlockFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'block_order',
        'raw_text',
        'structured_text',
        'screenplay_content',
        'user_locks',
        'stage',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_order' => 'integer',
            'user_locks' => 'array',
        ];
    }

    /**
     * Default model attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_locks' => '{}',
        'stage' => 'raw',
    ];

    /**
     * Scene to which this block belongs.
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * Shots linked directly to this script block.
     */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }
}
