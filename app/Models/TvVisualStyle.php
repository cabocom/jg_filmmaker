<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvVisualStyle extends Model
{
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'prompt_base',
        'characteristics',
        'reference_image_path',
        'is_default',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<TvRundown, $this> */
    public function rundowns(): HasMany
    {
        return $this->hasMany(TvRundown::class, 'visual_style_id');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'visual_style_id');
    }
}
