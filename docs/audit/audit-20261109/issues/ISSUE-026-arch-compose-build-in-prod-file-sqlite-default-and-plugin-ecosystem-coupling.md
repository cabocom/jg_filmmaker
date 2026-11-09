# [ISSUE-026] `docker-compose.yml` con `build:` Local y SQLite por Defecto, y `jg_plugin_filmmaker/docker-compose.yml` Acoplado al Ecosistema (Pendiente de `audit-20261018 / ISSUE-032, 034 & 039`)

- **Severidad:** Media
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/docker-compose.yml:6-20`, `jg_filmmaker/docker/entrypoint.sh:83-85`, `jg_plugin_filmmaker/docker-compose.yml:30-60`
- **Categoría:** Arquitectura

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-032`, `ISSUE-034` e `ISSUE-039`) se señalaron tres problemas de configuración de despliegue en contenedores que fueron resueltos solo de forma parcial:

1. **`build: context: .` y `DB_CONNECTION: sqlite` fijos en `jg_filmmaker/docker-compose.yml` (`ISSUE-032` & `ISSUE-034`):**
   - Aunque se retiraron los bind mounts de código hacia `docker-compose.dev.yml`, el archivo principal `jg_filmmaker/docker-compose.yml` sigue conteniendo `build: context: .` (líneas 6-8), lo que contradice el Principio de Despliegue Inmutable (`AGENTS.md §6.3`: prohibición de construir imágenes en producción).
   - Además, las líneas 18-20 fijan de forma estática `DB_CONNECTION: sqlite`, `DB_DATABASE: /var/www/html/database/database.sqlite` y `RUN_MIGRATIONS: "true"` sin admitir variables `${DB_CONNECTION:-pgsql}`, a pesar de que producción opera sobre PostgreSQL 16.
2. **Migraciones Automáticas sin Lock Distribuido en `docker/entrypoint.sh` (`ISSUE-034`):**
   - `docker/entrypoint.sh` (líneas 83-85) ejecuta `php artisan migrate --force` en cada arranque del contenedor siempre que `CURRENT_ENV = production`, sin adquirir un lock (`--isolated`) para prevenir colisiones si arrancan dos réplicas simultáneamente.
3. **Acoplamiento con `jg_dashboard` y Reutilización de Secreto Único en `jg_plugin_filmmaker/docker-compose.yml` (`ISSUE-031` & `ISSUE-039`):**
   - El `docker-compose.yml` del repositorio del plugin satélite sigue levantando `jg_dashboard` y `jg_dashboard_postgres` (componentes ajenos al plugin, violando `AGENTS.md §4`) y asigna la misma variable `${JG_AGENTE_SECRET}` a `JG_CONVERSATIONAL_SECRET` y `JG_DEVOPS_SECRET` (líneas 50-52).

## 2. Evidencia de Código
En [`jg_filmmaker/docker-compose.yml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docker-compose.yml#L5-L20):
```yaml
  jg_filmmaker:
    build:
      context: .
      dockerfile: Dockerfile
    image: jg_filmmaker:latest
    container_name: jg_filmmaker
    restart: unless-stopped
    environment:
      APP_NAME: "jG Filmmaker"
      APP_ENV: ${APP_ENV:-production}
      APP_KEY: "${APP_KEY:?Error: APP_KEY requerida. Genere una valida con php artisan key:generate}"
      APP_DEBUG: ${APP_DEBUG:-false}
      APP_URL: ${APP_URL:-http://localhost:8010}
      DB_CONNECTION: sqlite
      DB_DATABASE: /var/www/html/database/database.sqlite
      RUN_MIGRATIONS: "true"
```

En [`jg_plugin_filmmaker/docker-compose.yml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docker-compose.yml#L50-L52):
```yaml
      JG_AGENTE_SECRET: "${JG_AGENTE_SECRET:?Error: JG_AGENTE_SECRET requerida}"
      JG_CONVERSATIONAL_SECRET: "${JG_AGENTE_SECRET:?Error: JG_AGENTE_SECRET requerida}"
      JG_DEVOPS_SECRET: "${JG_AGENTE_SECRET:?Error: JG_AGENTE_SECRET requerida}"
```

## 3. Impacto Operativo o Riesgo
- **Riesgo de Compilación Accidental en Host y Desalineación de Motor BD:** Ejecutar `docker compose up -d --build` con el `docker-compose.yml` del repositorio compilaría en local/servidor y levantaría SQLite en lugar de PostgreSQL.

## 4. Solución o Mejora Recomendada
1. En `jg_filmmaker/docker-compose.yml`, retirar el bloque `build:` (manteniéndolo únicamente en `docker-compose.dev.yml`), parametrizar `DB_CONNECTION: ${DB_CONNECTION:-pgsql}` y usar `php artisan migrate --force --isolated` en `docker/entrypoint.sh`.
2. En `jg_plugin_filmmaker/docker-compose.yml`, eliminar los servicios `jg_dashboard` y `jg_dashboard_postgres`, dejando únicamente el arnés mínimo de prueba del plugin (`jg_agente_filmmaker` + `jg_agente_postgres`).

