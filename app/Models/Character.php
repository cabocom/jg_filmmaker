<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'bio',
        'psychology',
        'costume_notes',
        'reference_image_path',
        'custom_attributes',
    ];

    /**
     * Boot the model and register cascade cleanup for reference images.
     */
    protected static function booted(): void
    {
        static::deleted(function (Character $character): void {
            $refPath = $character->reference_image_path;
            if ($refPath) {
                DB::afterCommit(function () use ($refPath) {
                    if (Storage::disk('local')->exists($refPath)) {
                        Storage::disk('local')->delete($refPath);
                    }
                    if (Storage::disk('public')->exists($refPath)) {
                        Storage::disk('public')->delete($refPath);
                    }
                });
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_attributes' => 'array',
        ];
    }

    /**
     * Comprueba si el personaje tiene imagen de referencia / turn-around.
     */
    public function hasReferenceImage(): bool
    {
        return ! empty($this->reference_image_path);
    }

    /**
     * Retorna la URL protegida y autorizada para renderizar la imagen de referencia.
     */
    public function getReferenceImageUrlAttribute(): ?string
    {
        if (! $this->reference_image_path) {
            return null;
        }

        return route('media.character', ['character' => $this->id]);
    }

    /**
     * Project to which this character belongs.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Serializa el personaje con el contrato canónico requerido por los agentes cognitivos.
     *
     * @return array{name: string, bio: string, psychology: string, costume_notes: string, custom_attributes: array<string, mixed>}
     */
    public function toAgentContext(): array
    {
        return [
            'name' => (string) $this->name,
            'bio' => (string) ($this->bio ?? ''),
            'psychology' => (string) ($this->psychology ?? ''),
            'costume_notes' => (string) ($this->costume_notes ?? ''),
            'custom_attributes' => (array) ($this->custom_attributes ?? []),
        ];
    }
}
