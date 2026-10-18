# [ISSUE-027] Metadatos técnicos de tomas inventados (duración 5 s, 1080p, 24 fps) sin sondeo real del vídeo

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:1436-1445,1460-1469,1503-1520`, `app/Livewire/Studio/RoughCutTimeline.php:97,113-118,152-154`, `app/Services/RoughCutAssemblyService.php:31-33`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
Al subir un vídeo, `technical_meta` se rellena con valores por defecto fijos si faltan: `duration_seconds = 5.0`, `resolution = 1920x1080`, `fps = 24`, `codec = prores|h264` según la extensión. **Nunca se ejecuta `ffprobe`** sobre el fichero. Las tomas se crean con `3840x2160/prores422hq` por defecto aunque no haya vídeo. Consecuencias:
- El timeline, la duración total estimada y el EDL JSON exportado (`total_estimated_duration_seconds`) son ficticios.
- El `codec` se deduce de la extensión (`.mov` ⇒ `prores`), lo cual es falso en la mayoría de .mov (h264).
- La decisión `-c copy` del ensamblado no se apoya en datos reales (ISSUE-009).
- El EDL exportado entrega a postproducción metadatos incorrectos.

## 2. Evidencia de Código
```php
if (empty($currentMeta['duration_seconds'])) { $currentMeta['duration_seconds'] = 5.0; }
...
$currentMeta['codec'] = strtolower($extension) === 'mov' ? 'prores' : 'h264';
```

## 3. Impacto Operativo o de Seguridad
Información de montaje engañosa y fallos en la concatenación; decisiones editoriales basadas en datos falsos.

## 4. Solución Recomendada
- Job post-subida que ejecute `ffprobe -v error -print_format json -show_streams -show_format` con `Process::timeout(30)` y guarde `duration`, `width/height`, `r_frame_rate`, `codec_name`, `pix_fmt`, `bit_rate`.
- Marcar `technical_meta.probed = true|false`; mostrar "sin analizar" en la UI en lugar de valores inventados.
- Rechazar ficheros que `ffprobe` no reconozca como vídeo (segunda línea de defensa tras ISSUE-001).
- Tests con `Process::fake()`.
