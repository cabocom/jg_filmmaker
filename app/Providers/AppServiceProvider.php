<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prevención estricta en desarrollo y tests contra acceso a atributos de modelo inexistentes (CWE-1068 / ISSUE-012)
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Definición de Gate para acciones administrativas (ISSUE-021)
        Gate::define('admin', fn (User $user) => (bool) $user->is_admin);

        // Registro de middleware persistente en Livewire para asegurar que /livewire/update valide siempre el rol de admin (ISSUE-021)
        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware([
                EnsureUserIsAdmin::class,
            ]);
        }

        // Fail-fast en arranque (CWE-798): en producción no se admiten claves o secretos inseguros
        if ($this->app->isProduction()) {
            $secret = (string) config('services.jg_agente.secret', '');
            $knownInsecureSecrets = [
                'test-secret-ecosystem-2026',
                'secret',
                'changeme',
                '123456',
                'password',
            ];

            if (! (bool) config('services.jg_agente.mock', false)) {
                if (empty($secret)) {
                    throw new \RuntimeException(
                        'Seguridad (CWE-798): JG_AGENTE_SECRET (o PERMANENT_AGENT_SECRET) no está definido.'
                    );
                }

                if (strlen($secret) < 16 || in_array($secret, $knownInsecureSecrets, true)) {
                    throw new \RuntimeException(
                        'Seguridad (CWE-798): JG_AGENTE_SECRET utiliza un valor inseguro o de ejemplo en producción.'
                    );
                }
            }

            $appKey = (string) config('app.key', '');
            $knownInsecureKeys = [
                'base64:fH7i9N2bV5kM8qR3tY6wP1sL4xJ7aB0cDeFgHiJkLmN=',
            ];

            if (empty($appKey) || in_array($appKey, $knownInsecureKeys, true)) {
                throw new \RuntimeException(
                    'Seguridad (CWE-798): APP_KEY no configurada o utiliza una clave pública conocida versionada.'
                );
            }
        }
    }
}
