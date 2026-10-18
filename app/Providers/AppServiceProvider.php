<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
