<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityFailFastTest extends TestCase
{
    public function test_production_rejects_empty_jg_agente_secret(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', '');
        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $provider = new AppServiceProvider($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seguridad (CWE-798): JG_AGENTE_SECRET (o PERMANENT_AGENT_SECRET) no está definido.');

        $provider->boot();
    }

    public function test_production_rejects_insecure_known_jg_agente_secret(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'test-secret-ecosystem-2026');
        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $provider = new AppServiceProvider($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seguridad (CWE-798): JG_AGENTE_SECRET utiliza un valor inseguro o de ejemplo en producción.');

        $provider->boot();
    }

    public function test_production_rejects_short_jg_agente_secret(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'short-secret');
        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $provider = new AppServiceProvider($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seguridad (CWE-798): JG_AGENTE_SECRET utiliza un valor inseguro o de ejemplo en producción.');

        $provider->boot();
    }

    public function test_production_rejects_known_insecure_app_key(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', true);
        Config::set('app.key', 'base64:fH7i9N2bV5kM8qR3tY6wP1sL4xJ7aB0cDeFgHiJkLmN=');

        $provider = new AppServiceProvider($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seguridad (CWE-798): APP_KEY no configurada o utiliza una clave pública conocida versionada.');

        $provider->boot();
    }

    public function test_production_rejects_empty_app_key(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', true);
        Config::set('app.key', '');

        $provider = new AppServiceProvider($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seguridad (CWE-798): APP_KEY no configurada o utiliza una clave pública conocida versionada.');

        $provider->boot();
    }

    public function test_production_passes_with_valid_secure_credentials(): void
    {
        $this->app['env'] = 'production';
        Config::set('services.jg_agente.mock', false);
        Config::set('services.jg_agente.secret', 'a-very-long-and-secure-hmac-secret-string-2026!');
        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(true);
    }
}
