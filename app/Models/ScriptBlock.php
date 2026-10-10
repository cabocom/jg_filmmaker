<?php

namespace App\Models;

use Database\Factories\ScriptBlockFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScriptBlock extends Model
{
    /** @use HasFactory<ScriptBlockFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scene_id',
        'block_order',
        'raw_text',
        'structured_text',
        'screenplay_content',
        'user_locks',
        'stage',
        'plot_hole',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_order' => 'integer',
        ];
    }

    /**
     * Interact with the user_locks attribute to guarantee array type.
     */
    protected function userLocks(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                    if (is_string($decoded)) {
                        $second = json_decode($decoded, true);
                        if (is_array($second)) {
                            return $second;
                        }
                    }
                }

                return [];
            },
            set: function ($value): string {
                if (is_array($value)) {
                    return json_encode($value);
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return json_encode($decoded);
                    }
                }

                return json_encode([]);
            }
        );
    }

    /**
     * Default model attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_locks' => '{}',
        'stage' => 'raw',
    ];

    /**
     * Scene to which this block belongs.
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * Shots linked directly to this script block.
     */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }

    /**
     * Comprueba si una etapa específica está bloqueada contra regeneración de IA.
     */
    public function isStageLocked(string $stage): bool
    {
        $locks = $this->user_locks ?? [];
        $lockedFields = $locks['locked_fields'] ?? [];

        // Si se bloquea el guión, también queda bloqueado el tratamiento para que no se pueda regenerar desde el bruto
        if ($stage === 'structured' && in_array('screenplay', $lockedFields, true)) {
            return true;
        }

        return in_array($stage, $lockedFields, true);
    }

    /**
     * Comprueba si una etapa tiene modificaciones manuales registradas en manual_diffs.
     */
    public function hasManualOverride(string $stage): bool
    {
        $locks = $this->user_locks ?? [];
        foreach ($locks['manual_diffs'] ?? [] as $diff) {
            if (($diff['stage'] ?? null) === $stage) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprueba si el bloque tiene un agujero de guión detectado.
     */
    public function hasPlotHole(): bool
    {
        return filled($this->plot_hole);
    }
}
