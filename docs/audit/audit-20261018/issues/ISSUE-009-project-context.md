# Project Context: [ISSUE-009] Ensamblado FFmpeg robusto con reintento transcodificado, prevención de falsos positivos y retención

## Resumen del Cambio
Se corrigió el error en `RoughCutAssemblyService` donde la lista de concatenación se borraba antes del segundo intento de FFmpeg, lo que provocaba que la transcodificación de respaldo siempre fallara y generara un archivo de error falso pero reportado como exitoso a la interfaz. Se encapsuló la limpieza de archivos temporales dentro de `finally`, se introdujo generación de nombres por UUIDv4, saneamiento de rutas de tomas contra path traversal, purga automática de renders antiguos (máximo 5) y lanzamiento estricto de excepciones ante fallos reales de transcodificación.

## Archivos Modificados / Creados
- `app/Services/RoughCutAssemblyService.php`: Manejo con `try/finally`, UUIDs, validación de rutas, política de retención y eliminación de falsos positivos.
- `tests/Feature/RoughCutAssemblyTest.php`: Suite ampliada con simulación de fallback de códecs con `Process::fake()`, retención de archivos y excepciones.
- `docs/audit/audit-20261018/issues/ISSUE-009-analysis.md`: Análisis técnico y diagnóstico.
- `docs/audit/audit-20261018/issues/ISSUE-009-walkthrough.md`: Guía de implementación.
- `docs/audit/audit-20261018/issues/ISSUE-009-project-context.md`: Este documento de contexto.

## Impacto en Robustez y Medios
- **Fiabilidad del Pre-Corte:** Los cineastas obtienen renders funcionales con transcodificación automática ante disparidad de códecs, sin recibir archivos falsos de texto.
- **Control de Almacenamiento:** El espacio en disco de renders permanece acotado a 5 copias por proyecto.
- **Seguridad en Rutas:** Impedimento absoluto de concatenar archivos fuera del directorio de medios del proyecto.
