<?php

namespace App\Models;

use Database\Factories\TakeFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     * Boot the model and register deletion cleanup.
     */
    protected static function booted(): void
    {
        static::deleted(function (Take $take): void {
            if ($take->file_path) {
                $filePath = $take->file_path;
                DB::afterCommit(function () use ($filePath) {
                    if (Storage::disk('local')->exists($filePath)) {
                        Storage::disk('local')->delete($filePath);
                    }
                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                });
            }
        });
    }

    /**
     * Shot to which this take belongs.
     */
    public function shot(): BelongsTo
    {
        return $this->belongsTo(Shot::class);
    }

    /**
     * URL protegida al endpoint de streaming de vídeo de la toma.
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        return route('media.take', ['take' => $this->id]);
    }

    public const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v'];

    /**
     * Genera la ruta física particionada canónica por UUID.
     */
    public function generateStoragePath(string $extension = 'mp4'): string
    {
        $cleanExt = ltrim(strtolower($extension), '.');

        if (! in_array($cleanExt, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Extensión de archivo de vídeo no permitida: {$cleanExt}");
        }

        $this->loadMissing('shot.scene');
        $projectId = $this->shot?->scene?->project_id ?? 'unknown_project';
        $sceneId = $this->shot?->scene_id ?? 'unknown_scene';

        return "media/projects/{$projectId}/scenes/{$sceneId}/takes/{$this->id}.{$cleanExt}";
    }
}
