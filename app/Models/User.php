<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasUserTokenQuota;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUserTokenQuota, HasUuids, Notifiable;

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
     * Credenciales de API propias (BYOK - Bring Your Own Key) del usuario.
     */
    public function apiCredentials(): HasMany
    {
        return $this->hasMany(UserApiCredential::class);
    }
}
