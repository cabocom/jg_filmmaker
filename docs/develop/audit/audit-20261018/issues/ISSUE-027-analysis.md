# Análisis Técnico - ISSUE-027: Sondeo Técnico Real de Vídeo con FFprobe y Eliminación de Metadatos Ficticios

## 1. Contexto y Diagnóstico
Anteriormente, al subir una toma de vídeo en `ScriptEditor`, el sistema no ejecutaba ningún análisis sobre el fichero multimedia. En su lugar, inyectaba valores ficticios y arbitrarios por defecto:
- Duración fija de 5.0 segundos.
- Resolución fija de 1920x1080.
- Tasa de 24.0 fps.
- Códec inferido ingenuamente por la extensión del fichero (`.mov` => `prores`, otros => `h264`).

Además, `RoughCutTimeline::exportRoughCutJson()` y `render()` volvían a asumir 5.0 segundos o valores estáticos por defecto cuando faltaban los metadatos, falseando los reportes de pre-montaje, listas de cortes EDL y duración total del proyecto.

## 2. Solución Diseñada
1. **Sondeo real mediante FFprobe (`probeVideoFile`):**
   - Ejecución de `ffprobe -v error -print_format json -show_format -show_streams <path>` con `Process::timeout(15)`.
   - Extracción de streams de tipo `video` para obtener duración precisa (`round($duration, 2)`), resolución (`width x height`), fps exactos (calculados a partir de `r_frame_rate`), códec (`codec_name`), `pix_fmt` y `bit_rate`.
   - Marcado explícito de `probed = true|false`.
2. **Eliminación de metadatos ficticios:**
   - Si el archivo no existe o no se puede sondear, los campos técnicos permanecen como `null` y `probed = false`.
   - En `RoughCutTimeline`, no se fabrican valores por defecto ni en la suma de duración ni en la exportación estructurada JSON.

## 3. Estrategia de Pruebas
- `tests/Feature/VideoProbingAndRenderGraphTest.php`:
  - `test_upload_take_video_probes_media_with_ffprobe_safely`: emulación con `Process::fake()` de salida real de ffprobe y verificación de persistencia en `technical_meta`.
  - `test_rough_cut_export_does_not_fabricate_metadata_when_take_not_probed`: comprobación de ausencia de datos inventados.
