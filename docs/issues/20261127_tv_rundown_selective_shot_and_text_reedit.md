# Feat: Edición selectiva de viñetas y texto post-render con re-ensamblado incremental en escaletas TV

## Contexto y Necesidad
Actualmente, tras crear una `TvRundown` y renderizarla por primera vez:
- `TvRundownsPanel` solo permite editar el borrador inicial (`script_text`).
- Al relanzar la producción (`ManageTvRundownAction::dispatchBroadcastProduction`), `AutoGenerateBroadcastShotsAction::createProjectAndBlock()` elimina todos los `Shot` existentes (`$block->shots()->delete()`) y regenera desde cero tratamiento, guion, planos, audio e imágenes.
- El usuario necesita poder dar de alta la pill, generar el texto base, renderizarla y **después** poder:
  1. Editar la descripción visual de algunos shots que no le gusten y regenerar **solo** esas viñetas.
  2. Editar algunas líneas de texto a locutar (`oral_text` / `script_text`) conservando las viñetas ya generadas.
  3. Re-ensamblar el vídeo final reutilizando todo lo que no ha cambiado.

## Solución Propuesta
1. **Acción de negocio dedicada (`app/Actions/Tv/UpdateRundownShotsAction.php` o similar):**
   - `regenerateSingleShotVisual(TvRundown $rundown, string $shotId, ?string $newDescription = null)`: actualiza `shot->description` si se proporciona, llama a `StoryboardService::generateStoryboard($shot)` para generar la nueva imagen, e invalida únicamente `$artifacts['shot_visuals'][$shotId]` y `$artifacts['rendered_shots'][$shotId]` en el `BroadcastJob` (dejando intactos `master_audio` y los demás clips).
   - `updateOralTextsAndInvalidateAudio(TvRundown $rundown, array $shotOralTexts)`: actualiza el `oral_text` de los shots indicados, reconstruye `full_oral_text` y `rundown->script_text`, e invalida `$artifacts['master_audio']` y `$artifacts['rendered_shots']` manteniendo intactas todas las imágenes (`shot_visuals` y `shot->storyboard_image_path`).
   - `reassembleAfterEdits(TvRundown $rundown)`: marca `TvRundown` / `TvVideo` como `queued`/`rendering` y llama a `AutoGenerateBroadcastShotsAction::startRendering($job)` para renderizar únicamente lo pendiente y re-ensamblar el MP4 final.
2. **UI en `TvRundownsPanel` (`app/Livewire/Tv/TvRundownsPanel.php` + `resources/views/livewire/tv/rundowns-panel.blade.php`):**
   - Botón **"Viñetas"** en cada escaleta que ya tenga `broadcastJob` con shots.
   - Modal/panel de edición de viñetas mostrando por cada shot: número, miniatura de la imagen actual, textarea de `description` (visual) con botón **"Regenerar viñeta"**, textarea de `oral_text` (locución), y botón **"Guardar textos y re-ensamblar vídeo"** / **"Re-ensamblar vídeo"**.
3. **Tests:**
   - Tests Feature/Unit cubriendo: regeneración de un único shot manteniendo `master_audio` y otros `rendered_shots`, actualización de texto manteniendo `shot_visuals`, y disparo de re-ensamblado desde `TvRundownsPanel`.
