# Issue: Render Paralelo, Cámara Dinámica (Ken Burns) y Ensamblado Rápido FFmpeg

- **Fase:** Fase 3 — Motor Audio-First, Frases Completas y Atribución Visual
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 5 – 6 h (Media)
- **Estado:** Cerrado (Commit `66943be`, GitLab `jg_filmmaker#42`)

## Contexto y Motivación

El renderizado actual es estrictamente secuencial plano a plano (`~0.44x` tiempo real), utiliza imágenes completamente estáticas con bandas (`pad`), re-codifica el episodio entero una segunda vez en `AssembleBroadcastEpisodeAction::concatEpisode1080p()` (sin `-c copy`) y exporta siempre Podcast MP3 y Short 9:16 aunque no se hayan solicitado.

## Alcance Técnico (SOLID)

1. **Generación Paralela de Imágenes Pendientes con Ancla Visual (`GenerateBroadcastVisualsBatchAction`):**
   - Reutilizar las imágenes ya aprobadas en cada `Shot->storyboard_image_path`.
   - Generar primero el Plano 1 (*Master Establishing Frame*) si falta, y despachar en paralelo (`Bus::batch` / concurrencia controlada) los planos `2..N` pasando el Plano 1 como ancla de continuidad visual junto a la Biblia.
2. **Movimiento de Cámara Dinámico (`App\Services\Broadcast\MotionFilterBuilder`):**
   - Sustituir el encuadre estático con `pad` por `force_original_aspect_ratio=increase,crop=W:H` (16:9 *full-bleed* real) + filtro `zoompan` suave según `shot_type` (`PG` pan lateral, `PP` push-in suave, `PM` zoom ligero) y `-preset veryfast` en `720p30`.
3. **Ensamblado Rápido Audio-First (`AssembleBroadcastEpisodeAction`):**
   - Ensamblar la secuencia visual con `master_audio.wav` continuo + `.ass` global (subtítulos + crédito visual) evitando dobles recodificaciones innecesarias (`-c copy` donde proceda).
   - Condicionar `exportPodcastMp3` y `exportShort9x16` a los flags reales `publish_to_podcast` y `generate_short` del `BroadcastJob`.
4. **Tests y Documentación:**
   - Tests PHPUnit para `MotionFilterBuilder`, reutilización de imágenes aprobadas, flags condicionales de exportación y argumentos FFmpeg.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
