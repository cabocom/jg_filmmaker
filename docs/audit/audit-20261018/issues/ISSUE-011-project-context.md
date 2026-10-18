# Project Context: [ISSUE-011] Incorporación de `plot_hole_detector` en `jg_plugin_filmmaker`

## Resumen del Cambio
Se implementó y registró formalmente la herramienta `plot_hole_detector` en `jg_plugin_filmmaker`, eliminando la desconexión arquitectónica donde `jg_filmmaker` intentaba auditar agujeros de guión llamando a un endpoint no soportado por el plugin. Se crearon los esquemas Pydantic v2 correspondientes, el motor de análisis dramático semántico/heurístico en `plot_holes.py`, se registraron 4 herramientas canónicas en el catálogo y se actualizaron las pruebas unitarias y contextos de ambos proyectos.

## Archivos Modificados / Creados
- `jg_plugin_filmmaker/jg_plugin_filmmaker/plot_holes.py`: Motor de detección semántica y heurística.
- `jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py`: Definición de `PlotHoleDetectorArgs`, ejecutor y registro de la herramienta.
- `jg_plugin_filmmaker/jg_plugin_filmmaker/plugin.py`: Metadatos v2.3.0 y reporte de 4 tools.
- `jg_plugin_filmmaker/tests/test_filmmaker_tools.py` y `test_plugin.py`: Tests unitarios de plot holes y runner.
- `jg_plugin_filmmaker/PROJECT_CONTEXT.md`: Documentación técnica del nuevo contrato.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-011-analysis.md`: Análisis técnico.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-011-walkthrough.md`: Guía de implementación.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-011-project-context.md`: Este documento de contexto.

## Impacto en Arquitectura y Consistencia
- **Completitud del Contrato:** Los 4 pasos del pipeline (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector` y `plot_hole_detector`) cuentan ahora con respaldo pleno en el plugin cognitivo oficial.
- **Fin de Errores 404 Inter-Servicio:** `jg_agente` puede resolver válidamente las peticiones de auditoría enviadas por `jg_filmmaker`.
