<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'custom_attributes',
    ];

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
