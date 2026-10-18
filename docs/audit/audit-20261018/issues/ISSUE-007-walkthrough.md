# Walkthrough: [ISSUE-007] Eliminación de credenciales por defecto, fortalecimiento de Compose y fail-fast en arranque

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se han erradicado todas las credenciales y secretos débiles o de ejemplo versionados en archivos de configuración y orquestación:

1. **Blindaje de `docker-compose.yml`:**
   - Se eliminaron los valores hardcodeados por defecto de `APP_KEY` y `JG_AGENTE_SECRET`. Ahora se utiliza la directiva de Docker Compose `${VAR:?mensaje_de_error}` que exige explícitamente al operador proporcionar dichas variables, abortando el comando `docker compose up` si no existen.
   - `APP_ENV` pasa por defecto a `production` y `APP_DEBUG` a `false`.
   - `SEED_DEMO` pasa por defecto a `false`.

2. **Ajuste de `.env.example`:**
   - Se definieron `APP_DEBUG=false` y `LOG_LEVEL=info` por defecto, manteniendo vacíos `APP_KEY` y `JG_AGENTE_SECRET`.

3. **Fortalecimiento de `docker/entrypoint.sh`:**
   - Si `APP_KEY` no está configurada y el entorno es `production`, el script de arranque aborta inmediatamente con código de salida 1.
   - Las migraciones (`php artisan migrate --force`) ya no se ejecutan con `|| true`, garantizando que cualquier fallo en la estructura de base de datos impida levantar un contenedor con estado inconsistente.
   - La ejecución de seeders (`SEED_DEMO=true`) se ignora y se advierte en logs si el entorno es de producción, protegiendo las credenciales de usuarios reales.

4. **Fail-Fast en `AppServiceProvider::boot()`:**
   - En entorno `production`, la aplicación verifica que `APP_KEY` no esté vacía ni utilice la clave conocida pública del repositorio.
   - Si no está activo el mock (`services.jg_agente.mock = false`), valida que `JG_AGENTE_SECRET` tenga una longitud mínima de 16 caracteres y no corresponda a ninguno de los secretos conocidos o triviales (`test-secret-ecosystem-2026`, `secret`, `changeme`, etc.).

---

## 2. Modificaciones en el Código

### `docker-compose.yml`
```yaml
APP_ENV: ${APP_ENV:-production}
APP_KEY: "${APP_KEY:?Error: APP_KEY requerida. Genere una valida con php artisan key:generate}"
APP_DEBUG: ${APP_DEBUG:-false}
...
SEED_DEMO: "${SEED_DEMO:-false}"
JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:?Error: JG_AGENTE_SECRET requerido. Proporcione el secreto HMAC del ecosistema}"
```

### `docker/entrypoint.sh`
```sh
CURRENT_ENV="${APP_ENV:-production}"

if [ -z "$APP_KEY" ]; then
    if [ "$CURRENT_ENV" = "production" ]; then
        echo "❌ [Seguridad] APP_KEY obligatoria no configurada en producción. Abortando arranque."
        exit 1
    else
        echo "⚠️ [Seguridad] APP_KEY no configurada. Generando clave temporal de desarrollo..."
        php artisan key:generate --force || true
    fi
fi
...
php artisan migrate --force
if [ "$SEED_DEMO" = "true" ]; then
    if [ "$CURRENT_ENV" = "production" ]; then
        echo "⚠️ [Seguridad] SEED_DEMO=true ignorado en producción para proteger cuentas de usuario."
    else
        echo "🌱 Ejecutando seeders de demostración..."
        php artisan db:seed --force
    fi
fi
```

### `app/Providers/AppServiceProvider.php`
```php
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
            throw new \RuntimeException('Seguridad (CWE-798): JG_AGENTE_SECRET (o PERMANENT_AGENT_SECRET) no está definido.');
        }
        if (strlen($secret) < 16 || in_array($secret, $knownInsecureSecrets, true)) {
            throw new \RuntimeException('Seguridad (CWE-798): JG_AGENTE_SECRET utiliza un valor inseguro o de ejemplo en producción.');
        }
    }

    $appKey = (string) config('app.key', '');
    $knownInsecureKeys = [
        'base64:fH7i9N2bV5kM8qR3tY6wP1sL4xJ7aB0cDeFgHiJkLmN=',
    ];
    if (empty($appKey) || in_array($appKey, $knownInsecureKeys, true)) {
        throw new \RuntimeException('Seguridad (CWE-798): APP_KEY no configurada o utiliza una clave pública conocida versionada.');
    }
}
```

---

## 3. Pruebas Automatizadas

Se ha creado la suite unitaria `tests/Unit/SecurityFailFastTest.php` con 6 tests verificando el rechazo temprano ante:
1. `JG_AGENTE_SECRET` vacío en producción.
2. `JG_AGENTE_SECRET` con valor por defecto del repositorio (`test-secret-ecosystem-2026`).
3. `JG_AGENTE_SECRET` con longitud corta (< 16 caracteres).
4. `APP_KEY` con valor público conocido versionado.
5. `APP_KEY` vacía en producción.
6. Aceptación limpia de claves y secretos robustos.

Resultado: 6 tests pasando con 11 aserciones.
