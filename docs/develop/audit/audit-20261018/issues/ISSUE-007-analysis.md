# Análisis Técnico: [ISSUE-007] `docker-compose.yml` arranca con `APP_KEY` y secreto HMAC por defecto en el repositorio, `APP_ENV=local`, `APP_DEBUG=true` y demo sembrada

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidades:** CWE-798 (Use of Hard-coded Credentials), CWE-1188 (Insecure Default Initialization of Resource), CWE-215 (Insertion of Sensitive Information Into Debugging Code)

---

## 1. Diagnóstico del Estado Previo

1. **Valores Quemados en `docker-compose.yml`:**
   - `APP_KEY: "${APP_KEY:-base64:fH7i9N2bV5kM8qR3tY6wP1sL4xJ7aB0cDeFgHiJkLmN=}"` versionaba una clave simétrica estática conocida públicamente en el repositorio. Si un despliegue no fijaba `APP_KEY`, un atacante externo podía calcular firmas HMAC de Laravel, falsificar cookies de sesión, descifrar payloads cifrados y alterar snapshots de Livewire (RCE potencial vía deserialización/manipulación de estado de componentes).
   - `JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:-test-secret-ecosystem-2026}"` proveía un secreto HMAC de firma inter-servicio público por defecto, permitiendo a cualquier actor forjar firmas y realizar peticiones no autorizadas al microservicio `jg_agente` con la identidad de `jg_filmmaker`.
   - `APP_ENV: ${APP_ENV:-local}` y `APP_DEBUG: ${APP_DEBUG:-true}` activaban comportamiento de depuración por defecto (exposición de stack traces, rutas y variables de entorno ante excepciones).
   - `SEED_DEMO: "true"` ejecutaba los seeders de demostración en cada inicialización del contenedor, sembrando el usuario administrador de demostración (`filmmaker@cabocom.net`, `is_admin=true`).

2. **Silenciamiento de Errores en `docker/entrypoint.sh`:**
   - La directiva `php artisan migrate --force || true` silenciaba fallos críticos durante las migraciones, permitiendo que la aplicación se iniciara con esquemas corruptos o incompletos.
   - Si `APP_KEY` estaba vacía en producción, el script intentaba generar una clave local efímera (`php artisan key:generate`) en lugar de abortar el inicio del contenedor con un error fatal.

3. **Inexistencia de Validación de Claves Inseguras en `AppServiceProvider`:**
   - El bootloader solo verificaba `empty(config('services.jg_agente.secret'))`, admitiendo silenciosamente cadenas triviales o el valor por defecto versionado en producción.

---

## 2. Alternativas Evaluadas

1. **Mantener defaults en compose pero advertir en la documentación:**
   - *Descartada:* La práctica estándar DevSecOps prohíbe que el comportamiento por defecto de archivos de despliegue sea inseguro. Un administrador que ejecute `docker compose up` sin `.env` debe recibir un error explícito solicitando las claves requeridas, nunca un arranque con credenciales públicas.
2. **Requerimiento estricto con expansión `${VAR:?error}` y validación en `AppServiceProvider` y `entrypoint.sh` (Opción Seleccionada):**
   - *Ventajas:*
     - `docker-compose.yml` exige la provisión obligatoria de `APP_KEY` y `JG_AGENTE_SECRET` usando la sintaxis estándar de Docker Compose `${VAR:?mensaje_de_error}`.
     - `APP_ENV` y `APP_DEBUG` toman por defecto valores seguros de producción (`production` y `false`).
     - `SEED_DEMO` toma por defecto `false` y queda bloqueado en producción dentro de `entrypoint.sh`.
     - `entrypoint.sh` aborta con código de salida 1 si las migraciones fallan (`php artisan migrate --force` sin enmascaramiento `|| true`) y si falta `APP_KEY` en producción.
     - `AppServiceProvider` implementa un *fail-fast* riguroso en producción que rechaza claves conocidas/débiles tanto para `APP_KEY` como para `JG_AGENTE_SECRET`.

---

## 3. Plan de Implementación

1. **Configuración Docker Compose (`docker-compose.yml`):**
   - Eliminar secretos hardcodeados.
   - Forzar `APP_KEY: "${APP_KEY:?Error: APP_KEY es obligatoria. Genere una con php artisan key:generate}"`.
   - Forzar `JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:?Error: JG_AGENTE_SECRET es obligatorio}"`.
   - Configurar `APP_ENV: ${APP_ENV:-production}`, `APP_DEBUG: ${APP_DEBUG:-false}`, `SEED_DEMO: ${SEED_DEMO:-false}`.

2. **Plantilla de Entorno (`.env.example`):**
   - Establecer `APP_DEBUG=false`, `LOG_LEVEL=info` y dejar `APP_KEY=` y `JG_AGENTE_SECRET=` vacíos con comentarios de orientación.

3. **Script de Entrada de Contenedor (`docker/entrypoint.sh`):**
   - Abortar arranque si `APP_KEY` no está definida y `CURRENT_ENV=production`.
   - Ejecutar `php artisan migrate --force` sin supresión de errores (`|| true`).
   - Omitir `SEED_DEMO` si el entorno es producción, advirtiendo en logs.

4. **Fail-Fast en Bootloader (`app/Providers/AppServiceProvider.php`):**
   - Validar que ni `APP_KEY` ni `JG_AGENTE_SECRET` utilicen los valores de ejemplo inseguros previamente versionados en el repositorio cuando `app()->isProduction()`.
   - Validar longitud mínima de `JG_AGENTE_SECRET` (mínimo 16 caracteres en producción).

5. **Pruebas Automatizadas (`tests/Unit/SecurityFailFastTest.php`):**
   - Validar que `AppServiceProvider` arroja `RuntimeException` ante secretos y claves inseguras cuando la aplicación está en modo producción.
