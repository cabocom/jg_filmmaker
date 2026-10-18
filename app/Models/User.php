<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan_tier',
        'paid_project_slots',
        'is_admin',
        'daily_token_limit',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'paid_project_slots' => 'integer',
            'is_admin' => 'boolean',
            'daily_token_limit' => 'integer',
        ];
    }

    /**
     * Projects owned by the user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * One time passwords generated for this user.
     */
    public function oneTimePasswords(): HasMany
    {
        return $this->hasMany(OneTimePassword::class);
    }

    /**
     * Security breach logs related to this user.
     */
    public function securityBreachLogs(): HasMany
    {
        return $this->hasMany(SecurityBreachLog::class);
    }

    /**
     * Activity logs for user actions and audit trail.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    /**
     * AI agent request logs executed by or attributed to this user.
     */
    public function agentRequestLogs(): HasMany
    {
        return $this->hasMany(AgentRequestLog::class);
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
    public function getTokensUsedToday(): int
    {
        return (int) DB::table('agent_request_logs')
            ->leftJoin('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->where(function ($query) {
                $query->where('agent_request_logs.user_id', $this->id)
                    ->orWhere(function ($q) {
                        $q->whereNull('agent_request_logs.user_id')
                            ->where('projects.user_id', $this->id);
                    });
            })
            ->where('agent_request_logs.created_at', '>=', now()->startOfDay())
            ->sum('agent_request_logs.total_tokens');
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
