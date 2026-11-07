<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Comprueba si la localización tiene imagen de referencia escenográfica.
     */
    public function hasReferenceImage(): bool
    {
        return ! empty($this->reference_image_path);
    }

    /**
     * Retorna la URL pública accesible para renderizar la imagen de referencia.
     */
    public function getReferenceImageUrlAttribute(): ?string
    {
        if (! $this->reference_image_path) {
            return null;
        }

        return asset('storage/'.ltrim($this->reference_image_path, '/'));
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
