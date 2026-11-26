# Issue: Unificación 1:1 de Proyecto entre `TvRundown` y `BroadcastJob` y resiliencia visual sin pantallas negras

- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 2 – 3 h (Media)
- **Estado:** Cerrado (commit `d3797c5`)

## Contexto y Motivación

1. **Proyecto duplicado entre Escaleta IA y Estudio:** `ManageTvRundownAction::createFromPromptOrSource()` crea un `Project` (`Videos IA`) vinculado a la escaleta, pero `dispatchBroadcastProduction()` llama a `AutoGenerateBroadcastShotsAction::initialize()` sin pasar `project_id`, creando un segundo `Project` oculto donde se generan Tratamiento, Biblia y `Shots`, dejando el proyecto al que apunta `🎬 Estudio` congelado en `RAW`.
2. **Pantallas negras silenciosas en fallos de plano:** Cuando un plano #2+ falla en `StoryboardShotVisual::generateVisual()`, la excepción se silencia sin log y cae en `StaticCardShotVisual` (que genera un PNG oscuro/negro en ausencia de GD/FFmpeg), provocando segundos finales en negro en el vídeo final.

## Alcance Técnico (SOLID)

1. **Reutilización 1:1 de `Project` en `AutoGenerateBroadcastShotsAction`:**
   - Añadir `?string $projectId = null` a `BroadcastJobRequestDTO`.
   - En `AutoGenerateBroadcastShotsAction::createProjectAndBlock()`, si `$dto->projectId` existe, reutilizar el `Project` existente (actualizando su `storyboard_style` / `visual_aesthetic_config` y reutilizando o creando `Scene` y `ScriptBlock` sin duplicar proyecto).
   - Pasar `projectId: $rundown->project_id` desde `ManageTvRundownAction::dispatchBroadcastProduction()`.
2. **Resiliencia visual en `StoryboardShotVisual`:**
   - Registrar en `Log::warning` el motivo exacto si falla la generación con imagen de referencia.
   - Reintentar automáticamente una segunda llamada a `StoryboardService::generateStoryboard()` **sin** `styleReferenceImageBase64` antes de recurrir al fallback.
   - Si aun así falla, reutilizar el último `storyboard_image_path` válido de un plano previo del mismo `ScriptBlock` (si existe) antes de recurrir a `StaticCardShotVisual`, evitando fotogramas negros abruptos.
3. **Tests:**
   - Test PHPUnit verificando que `ManageTvRundownAction::dispatchBroadcastProduction()` reutiliza el mismo `project_id` 1:1 del `TvRundown` sin crear proyectos duplicados.
   - Test PHPUnit verificando el reintento sin imagen de referencia y reutilización de plano previo en `StoryboardShotVisual`.
