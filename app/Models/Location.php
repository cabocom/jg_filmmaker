<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'type',
        'art_department_notes',
        'lighting_notes',
        'address_reference',
        'reference_image_path',
    ];

    /**
     * Boot the model and register cascade cleanup for reference images.
     */
    protected static function booted(): void
    {
        static::deleted(function (Location $location): void {
            $refPath = $location->reference_image_path;
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
     * Comprueba si la localización tiene imagen de referencia escenográfica.
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

        return route('media.location', ['location' => $this->id]);
    }

    /**
     * Project to which this location belongs.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scenes taking place at this location.
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * Serializa la localización con el contrato canónico requerido por los agentes cognitivos.
     *
     * @return array{name: string, type: string, art_department_notes: string, lighting_notes: string, address_reference: string}
     */
    public function toAgentContext(): array
    {
        return [
            'name' => (string) $this->name,
            'type' => (string) ($this->type ?? 'INT'),
            'art_department_notes' => (string) ($this->art_department_notes ?? ''),
            'lighting_notes' => (string) ($this->lighting_notes ?? ''),
            'address_reference' => (string) ($this->address_reference ?? ''),
        ];
    }
}
