<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\UserApiCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasUserTokenQuota
{
    /**
     * Comprueba si el usuario tiene una credencial BYOK activa para un proveedor.
     */
    public function hasActiveByokCredential(string $provider): bool
    {
        return $this->apiCredentials()
            ->where('provider', strtolower($provider))
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Obtiene la credencial BYOK activa para un proveedor específico.
     */
    public function getActiveCredential(string $provider): ?UserApiCredential
    {
        return $this->apiCredentials()
            ->where('provider', strtolower($provider))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Cupo diario configurado de tokens (por defecto 100.000).
     */
    public function getDailyTokenLimit(): int
    {
        return (int) ($this->daily_token_limit ?? 100000);
    }

    /**
     * Tokens consumidos hoy por todas las peticiones a la IA en los proyectos del usuario.
     */
    public function getTokensUsedToday(bool $fresh = false): int
    {
        $cacheKey = "user:{$this->id}:tokens_used_today";
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return (int) Cache::remember($cacheKey, 30, function () {
            return (int) DB::table('agent_request_logs')
                ->leftJoin('projects', 'agent_request_logs.project_id', '=', 'projects.id')
                ->where(function ($query) {
                    $query->where('agent_request_logs.user_id', $this->id)
                        ->orWhere(function ($q) {
                            $q->whereNull('agent_request_logs.user_id')
                                ->where('projects.user_id', $this->id);
                        });
                })
                ->where('agent_request_logs.is_byok', false)
                ->where('agent_request_logs.created_at', '>=', now()->startOfDay())
                ->sum('agent_request_logs.total_tokens');
        });
    }

    /**
     * Tokens disponibles restantes para el día en curso (se refrescan automáticamente cada medianoche).
     */
    public function getTokensAvailableToday(): int
    {
        return max(0, $this->getDailyTokenLimit() - $this->getTokensUsedToday());
    }

    /**
     * Comprueba si el usuario tiene saldo de tokens diarios disponibles.
     */
    public function hasDailyTokensAvailable(int $estimated = 1): bool
    {
        return $this->getTokensAvailableToday() >= $estimated;
    }
}
