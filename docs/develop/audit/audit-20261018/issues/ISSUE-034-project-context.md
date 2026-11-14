# Project Context Update - ISSUE-034

## Componente Afectado
- `jg_filmmaker` (v3.15.0)

## Resumen de Cambios
- Desacoplamiento de `docker-compose.yml` (producción inmutable sin bind mounts) y `docker-compose.dev.yml` (desarrollo local en caliente), y saneamiento de permisos en `entrypoint.sh`.
