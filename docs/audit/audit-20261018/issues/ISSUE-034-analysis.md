# Análisis Técnico - ISSUE-034: Blindaje del Despliegue Inmutable en Docker Compose

## 1. Contexto y Diagnóstico
1. `docker-compose.yml` contenía montajes tipo bind mount (`./app`, `./config`, `./resources`, `./routes`, `./.env`) sobre el contenedor de aplicación. Esto violaba directamente el Principio de Despliegue Inmutable (AGENTS.md §6.3): en producción, el código en ejecución difería de los artefactos compilados en la imagen Docker, imposibilitando rollbacks reproducibles y exponiendo ficheros del host.
2. `docker/entrypoint.sh` aplicaba permisos `chmod -R 775` y `chown` innecesarios sobre directorios estáticos de código (`resources`).

## 2. Solución Diseñada
1. **Desacoplamiento de entornos Compose:**
   - `docker-compose.yml`: reservado exclusivamente para producción/staging, sin bind mounts de código fuente (únicamente volúmenes persistentes de datos `storage` y `database`).
   - `docker-compose.dev.yml`: archivo dedicado para desarrollo local en caliente con montajes de código activos mediante override (`docker compose -f docker-compose.yml -f docker-compose.dev.yml up`).
2. **Saneamiento de permisos en `entrypoint.sh`:**
   - Confinamiento de permisos dinámicos 775 exclusivamente a `storage`, `bootstrap/cache` y `database`, preservando el código inmutable.

## 3. Estrategia de Pruebas
- Validación de sintaxis Compose con `docker compose config`.
- Verificación de CI/CD sin regresiones en las suites automatizadas.
