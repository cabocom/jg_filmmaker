<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Project;
use App\Models\User;
use App\Models\UserApiCredential;
use InvalidArgumentException;
use SensitiveParameter;

class ProjectEncryptionVaultService
{
    /**
     * Proveedores de inferencia y modelos cognitivos admitidos en BYOK.
     */
    public const SUPPORTED_PROVIDERS = ['google', 'groq', 'openai', 'anthropic'];

    /**
     * Almacena o actualiza de forma cifrada una clave de API propia para el usuario.
     */
    public function storeCredential(
        User $user,
        string $provider,
        #[SensitiveParameter] string $apiKey
    ): UserApiCredential {
        $normalizedProvider = strtolower(trim($provider));
        if (! in_array($normalizedProvider, self::SUPPORTED_PROVIDERS, true)) {
            throw new InvalidArgumentException("Proveedor no soportado: {$provider}");
        }

        $trimmedKey = trim($apiKey);
        if ($trimmedKey === '') {
            throw new InvalidArgumentException('La clave de API no puede estar vacía.');
        }

        $fingerprint = UserApiCredential::generateFingerprint($trimmedKey);

        return UserApiCredential::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $normalizedProvider,
            ],
            [
                'encrypted_api_key' => $trimmedKey,
                'key_fingerprint' => $fingerprint,
                'is_active' => true,
            ]
        );
    }

    /**
     * Resuelve la credencial BYOK activa para un usuario y proveedor.
     */
    public function resolveCredentialForUser(User $user, string $provider): ?UserApiCredential
    {
        return $user->getActiveCredential($provider);
    }

    /**
     * Resuelve la credencial BYOK activa para el usuario propietario de un proyecto.
     */
    public function resolveCredentialForProject(?Project $project, string $provider): ?UserApiCredential
    {
        if (! $project || ! $project->user) {
            return null;
        }

        return $this->resolveCredentialForUser($project->user, $provider);
    }

    /**
     * Comprueba si el usuario dispone de credencial activa para el proveedor indicado.
     */
    public function hasActiveCredential(User $user, string $provider): bool
    {
        return $user->hasActiveByokCredential($provider);
    }

    /**
     * Revoca o desactiva la credencial BYOK del usuario para el proveedor indicado.
     */
    public function revokeCredential(User $user, string $provider): bool
    {
        $credential = $user->apiCredentials()
            ->where('provider', strtolower(trim($provider)))
            ->first();

        if (! $credential) {
            return false;
        }

        return $credential->update(['is_active' => false]);
    }
}
