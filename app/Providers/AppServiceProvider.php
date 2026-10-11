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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail-fast en arranque (CWE-798): en producción no se admite un cliente jg_agente sin secreto HMAC.
        if ($this->app->isProduction()
            && ! (bool) config('services.jg_agente.mock', false)
            && empty(config('services.jg_agente.secret'))) {
            throw new \RuntimeException(
                'Seguridad (CWE-798): JG_AGENTE_SECRET (o PERMANENT_AGENT_SECRET) no está definido.'
            );
        }
    }
}
