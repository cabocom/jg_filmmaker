# Project Context Update - ISSUE-027

## Componente Afectado
- `jg_filmmaker` (v3.13.0)

## Resumen de Cambios
- Sustitución de valores técnicos ficticios (5s, 1080p, 24fps) por sondeo real del contenedor y streams mediante `ffprobe` en `ScriptEditor::probeVideoFile`.
- Saneamiento de exportación JSON en `RoughCutTimeline` con soporte para tomas no analizadas (`probed = false`).
