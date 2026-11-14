# Project Context Update - ISSUE-040

## Componente Afectado
- `jg_filmmaker` (v3.16.0)

## Resumen de Cambios
- Introducción de `Prunable` en `UserActivityLog` (90 días), deduplicación de snapshots de guión en `ScriptEditor` y purga en cascada en `Project::deleting`.
