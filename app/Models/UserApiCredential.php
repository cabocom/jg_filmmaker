<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SensitiveParameter;

class UserApiCredential extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'encrypted_api_key',
        'key_fingerprint',
        'is_active',
    ];

    protected $hidden = [
        'encrypted_api_key',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Genera la huella enmascarada (últimos 4 caracteres) para representación segura en UI.
     */
    public static function generateFingerprint(#[SensitiveParameter] string $key): string
    {
        $trimmed = trim($key);
        $length = strlen($trimmed);

        if ($length <= 4) {
            return '...'.$trimmed;
        }

        return '...'.substr($trimmed, -4);
    }

    /**
     * Devuelve la clave en texto plano desencriptada exclusivamente en memoria.
     */
    public function getDecryptedKey(): string
    {
        return (string) $this->encrypted_api_key;
    }
}
