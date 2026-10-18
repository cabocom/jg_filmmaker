# [ISSUE-007] `docker-compose.yml` arranca con `APP_KEY` y secreto HMAC por defecto en el repositorio, `APP_ENV=local`, `APP_DEBUG=true` y demo sembrada

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `docker-compose.yml:14-16,21,24`, `.env.example:2,4,21`, `docker/entrypoint.sh:76-80`
- **Categoría:** Seguridad

## 1. Descripción del Problema
- `APP_KEY: "${APP_KEY:-base64:[valor fijo versionado]}"` y `JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:-[valor fijo versionado]}"`: si el despliegue omite las variables, el contenedor arranca con claves **públicas conocidas** (CWE-798). Con APP_KEY conocida, un atacante firma cookies/sesiones y snapshots de Livewire; con el secreto conocido falsifica llamadas HMAC a `jg_agente`. El fail-fast de `AppServiceProvider` solo valida que el secreto no esté vacío, por lo que **no detecta** estos valores por defecto.
- `APP_ENV` por defecto `local` y `APP_DEBUG` `true`: activa la rama "entorno local" del login (ISSUE-002) y páginas de error con stack traces y variables de entorno. El entrypoint solo fuerza `APP_DEBUG=false` si `APP_ENV=production`.
- `SEED_DEMO: "true"` fijo: siembra un usuario administrador conocido (`filmmaker@cabocom.net`, `is_admin=true`) en cada arranque.
- `php artisan migrate --force || true`: un fallo de migración se silencia y la app arranca con esquema incompleto.
- El CI hace `cp .env.example .env` (APP_ENV=local, LOG_LEVEL=debug), así que la imagen y los tests no validan nunca la configuración de producción.

## 2. Evidencia de Código
```yaml
APP_ENV: ${APP_ENV:-local}
APP_KEY: "${APP_KEY:-base64:...}"        # valor versionado
APP_DEBUG: ${APP_DEBUG:-true}
SEED_DEMO: "true"
JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:-...}"   # valor versionado
```
```sh
php artisan migrate --force || true
```

## 3. Impacto Operativo o de Seguridad
Falsificación de sesión/cookies y snapshots Livewire, impersonación del servicio ante `jg_agente`, divulgación de secretos vía debug, cuenta admin conocida y esquema inconsistente tras despliegues fallidos.

## 4. Solución Recomendada
- Variables obligatorias sin valor por defecto: `APP_KEY: ${APP_KEY:?APP_KEY requerida}`, `JG_AGENTE_SECRET: ${JG_AGENTE_SECRET:?...}`, `APP_ENV: ${APP_ENV:-production}`, `APP_DEBUG: ${APP_DEBUG:-false}`, `SEED_DEMO: ${SEED_DEMO:-false}`.
- Rotar los valores que ya han estado versionados.
- En `AppServiceProvider::boot()` rechazar en producción secretos con longitud < 32 o presentes en una lista de valores conocidos.
- Quitar `|| true` de `migrate` (el contenedor debe fallar de forma visible).
- Perfil de CI "prod-like" (`APP_ENV=production`) para validar el arranque.
- Rotar/regenerar `APP_KEY` invalida sesiones y OTPs en curso: planificarlo.
