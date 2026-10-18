<?php

namespace App\Models;

use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'scene_order',
        'slugline',
        'location_id',
        'time_of_day',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scene_order' => 'integer',
        ];
    }

    /**
     * Project to which this scene belongs.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Location associated with this scene.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Script blocks belonging to this scene.
     */
    public function scriptBlocks(): HasMany
    {
        return $this->hasMany(ScriptBlock::class)->orderBy('block_order');
    }

    /**
     * Shots planned for this scene.
     */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class)->orderBy('shot_number');
    }
}
