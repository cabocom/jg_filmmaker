<?php

namespace App\Models;

use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     * Boot the model and register cascade media cleanup.
     */
    protected static function booted(): void
    {
        static::deleting(function (Scene $scene): void {
            $filePaths = Take::whereHas('shot', fn ($q) => $q->where('scene_id', $scene->id))
                ->whereNotNull('file_path')
                ->pluck('file_path')
                ->all();

            if (! empty($filePaths)) {
                DB::afterCommit(function () use ($filePaths) {
                    foreach ($filePaths as $path) {
                        if (Storage::disk('local')->exists($path)) {
                            Storage::disk('local')->delete($path);
                        }
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                });
            }
        });
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
