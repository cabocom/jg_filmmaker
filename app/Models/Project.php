<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'synopsis',
        'status',
        'storyboard_style',
    ];

    /**
     * Boot the model and register cascade directory cleanup.
     */
    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            $project->activityLogs()->delete();
        });

        static::deleted(function (Project $project): void {
            $projectId = $project->id;
            DB::afterCommit(function () use ($projectId) {
                if (Storage::disk('local')->exists("media/projects/{$projectId}")) {
                    Storage::disk('local')->deleteDirectory("media/projects/{$projectId}");
                }
                if (Storage::disk('public')->exists("media/projects/{$projectId}")) {
                    Storage::disk('public')->deleteDirectory("media/projects/{$projectId}");
                }
            });
        });
    }

    /**
     * User who owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Characters defined in the project bible.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * Locations defined in the project bible.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Scenes belonging to the project.
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('scene_order');
    }

    /**
     * Agent request telemetry logs for this project.
     */
    public function agentRequestLogs(): HasMany
    {
        return $this->hasMany(AgentRequestLog::class);
    }

    /**
     * Activity logs for user actions in this project.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }
}
