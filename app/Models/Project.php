<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectCategory;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'visual_aesthetic_config',
        'category',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ProjectCategory::class,
            'visual_aesthetic_config' => 'array',
        ];
    }

    /**
     * Resolve the project category as an enum instance.
     */
    public function categoryEnum(): ProjectCategory
    {
        return $this->category instanceof ProjectCategory
            ? $this->category
            : ProjectCategory::fromNullable(is_string($this->category) ? $this->category : null);
    }

    /**
     * Scope projects by category.
     */
    public function scopeCategory(Builder $query, ProjectCategory|string $category): Builder
    {
        $value = $category instanceof ProjectCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

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
                foreach (["media/projects/{$projectId}", "projects/{$projectId}"] as $dir) {
                    if (Storage::disk('local')->exists($dir)) {
                        Storage::disk('local')->deleteDirectory($dir);
                    }
                    if (Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->deleteDirectory($dir);
                    }
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
