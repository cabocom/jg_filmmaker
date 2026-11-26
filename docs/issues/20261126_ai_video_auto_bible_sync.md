# Issue: Sincronización automática de Biblia Contextual en Proyectos de Vídeo IA

- **Fase:** Fase 1 — Fundación de Proyectos Vídeos IA
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 3 – 4 h (Baja-Media)
- **Estado:** Abierto

## Contexto y Motivación

Actualmente en `AutoGenerateBroadcastShotsAction::processTreatmentStage()` y `processScreenplayStage()` se invoca `ScriptPipelineService` con `syncProjectContext: false`. Como resultado, los proyectos de vídeo IA se generan sin Personajes ni Localizaciones en su Biblia Contextual, impidiendo que `StoryboardService` y `StoryboardPromptBuilder` inyecten consistencia visual o referencias de personajes/escenarios al generar las imágenes de cada plano.

## Alcance Técnico (SOLID)

1. **Acción dedicada de Sincronización de Biblia Broadcast (`App\Actions\Broadcast\SyncBroadcastProjectBibleAction`):**
   - Extraer y poblar automáticamente los `Character` y `Location` del `Project` (`category = ai_video`) a partir del texto del bloque (`raw_text` / `structured_text` / `screenplay_content`) apoyándose en `BibleSyncService` y/o perfiles base del canal cuando no existan entidades previas.
   - Vincular la `Scene` del proyecto con la `Location` principal detectada (`scene->location_id`) para que `StoryboardPromptBuilder::resolveLocationModel()` resuelva automáticamente la escenografía e iluminación.
   - Proteger la ejecución con tolerancia a fallos (*best-effort* con logging estructurado) para no abortar el pipeline broadcast si el extractor heurístico/LLM no encuentra entidades.
2. **Integración en `AutoGenerateBroadcastShotsAction`:**
   - Invocar `SyncBroadcastProjectBibleAction` durante las etapas editoriales (`TREATMENT` / `SCREENPLAY`) y registrar en `$job->artifacts['bible_summary']` los personajes y localizaciones vinculados (`characters_count`, `locations_count`, nombres).
   - Permitir también pasar perfiles iniciales opcionales de personaje/localización (`cast_profiles` / `location_profile`) en `BroadcastJobRequestDTO` si el cliente M2M los proporciona.
3. **Propagación a `StoryboardService`:**
   - Garantizar que al generar imágenes de los `Shots` del proyecto `ai_video`, `StoryboardPromptBuilder` recibe automáticamente el `Character` y la `Location` de la Biblia recién sincronizada (incluyendo `reference_image_path` si el usuario las sube desde el Estudio).
4. **Tests y Documentación:**
   - Tests PHPUnit verificando que un proyecto `ai_video` crea automáticamente Personajes y Localizaciones en BD, vincula `scene.location_id`, guarda `bible_summary` en `artifacts` y alimenta el prompt de `StoryboardPromptBuilder`.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
