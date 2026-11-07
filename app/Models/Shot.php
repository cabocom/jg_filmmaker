<?php

namespace App\Models;

use Database\Factories\ShotFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shot extends Model
{
    /** @use HasFactory<ShotFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'script_block_id',
        'shot_number',
        'shot_type',
        'description',
        'storyboard_image_path',
        'storyboard_style',
        'storyboard_prompt',
    ];

    /**
     * Comprueba si el plano ya tiene viñeta de storyboard generada.
     */
    public function hasStoryboard(): bool
    {
        return ! empty($this->storyboard_image_path);
    }

    /**
     * Retorna la URL pública o relativa para renderizar la viñeta de storyboard.
     */
    public function getStoryboardUrlAttribute(): ?string
    {
        if (! $this->storyboard_image_path) {
            return null;
        }

        return asset('storage/'.ltrim($this->storyboard_image_path, '/'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shot_number' => 'integer',
        ];
    }

    /**
     * Scene to which this shot belongs.
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * Script block from which this shot originated (if any).
     */
    public function scriptBlock(): BelongsTo
    {
        return $this->belongsTo(ScriptBlock::class);
    }

    /**
     * Takes recorded for this technical shot.
     */
    public function takes(): HasMany
    {
        return $this->hasMany(Take::class)->orderBy('take_number');
    }
}
