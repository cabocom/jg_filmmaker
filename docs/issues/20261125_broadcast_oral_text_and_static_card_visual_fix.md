# Issue: Locución real de `raw_text` y eliminación de pantalla verde en `StaticCardShotVisual`

- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Estado:** Abierto

## Alcance Técnico

1. **Distribución de `oral_text` desde `$job->raw_text`:**
   - En `AutoGenerateBroadcastShotsAction::processShotsStage()`, repartir `$job->raw_text` por frases entre los planos sanitizados (`$sanitized[$i]['oral_text']`), garantizando que cada plano tenga el segmento real del guion en el idioma original.
   - En `RenderShotClipAction::execute()`, usar `$context['oral_text']` o en su defecto `$job->raw_text` / `$job->title`, evitando locutar `$shot->description` (instrucciones técnicas de cámara).
2. **Eliminación de pantalla verde en `StaticCardShotVisual`:**
   - Cuando `imagecreatetruecolor` (`gd`) no esté disponible, generar el PNG de la tarjeta (1280x720 o 1920x1080, fondo `#0f172a`) con `ffmpeg`, y sustituir el PNG 1x1 verde de `renderFallbackPng()` por un PNG 1x1 oscuro (`#0f172a`).
3. **Tests y documentación:**
   - Añadir tests PHPUnit que verifiquen que `oral_text` proviene de `raw_text` (no de `Shot->description`) y que `StaticCardShotVisual` no genera PNG verde.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
