# [ISSUE-034] `docker-compose.yml` viola el despliegue inmutable: `build:` local, bind mounts del código y `RUN_MIGRATIONS`/seed en arranque

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `docker-compose.yml:6-9,26-37`, `docker/entrypoint.sh:76-80`, `deploy.sh`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
- El compose define `build: context: .` y monta `./app`, `./config`, `./resources`, `./routes` y `./.env` dentro del contenedor. Si se usa tal cual en `webdev.cabocom.com`: (a) se **construye en el servidor** (prohibido por AGENTS.md §6.3), (b) el código en ejecución **no es el de la imagen publicada** sino el del directorio del host (deriva entre CI y producción, imposible de reproducir/rollback), (c) el `.env` real con secretos queda accesible en el host y se monta en el contenedor.
- `RUN_MIGRATIONS: "true"` y `SEED_DEMO: "true"` fijos y `php artisan migrate --force || true` ejecutan migraciones y seeders en cada arranque por cada réplica; sin bloqueo (dos contenedores migran a la vez) y con errores silenciados (ISSUE-007).
- El entrypoint ejecuta como root `chown -R`/`chmod -R 775` sobre `storage`, `resources` y `database` (permisos 775 recursivos innecesarios en `resources`), y `rm -rf` de `framework/views` y caché en cada arranque.
- `ffmpeg` se instala dentro de la imagen web (imagen más pesada); el ensamblado de vídeo comparte CPU con el servidor web (ISSUE-009).

## 2. Evidencia de Código
```yaml
build:
  context: .
volumes:
  - ./app:/var/www/html/app
  - ./.env:/var/www/html/.env:ro
```

## 3. Impacto Operativo o de Seguridad
Pérdida de reproducibilidad y de la garantía de que lo probado en CI es lo desplegado; riesgo de migraciones concurrentes y de exposición de secretos.

## 4. Solución Recomendada
- Separar `docker-compose.dev.yml` (build + bind mounts) de `docker-compose.yml`/`docker-compose.prod.yml` (sólo `image: registry.gitlab.com/...:<tag>`, sin volúmenes de código, secretos por `env_file` fuera del repositorio o Docker secrets).
- Ejecutar migraciones en un job/servicio one-shot (`docker compose run --rm migrate`) con bloqueo, nunca con `|| true`.
- `SEED_DEMO` sólo en dev.
- Etiquetas inmutables por commit (no sólo `:latest`) y rollback documentado en `deploy.sh`.
- Separar un worker (cola) con FFmpeg del contenedor web.
