# Issue: Consola Nativa de Televisión (`/tv`) en `jg_filmmaker` vinculada 1:1 a Proyectos `Videos IA`

- **Fase:** Fase 4 — Desacoplamiento de Televisión y Migración a `jg_filmmaker`
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 7 – 9 h (Alta)
- **Estado:** Abierto

## Contexto y Motivación

La consola de Televisión (Parrilla 24h, Escaletas IA, Vídeos, Publicidad y Configuración de Canal) reside actualmente en `jg_hub`, acoplando un portal público con lógica de producción audiovisual y duplicando estado respecto a `jg_filmmaker`. Al trasladarla a `jg_filmmaker`, cada Escaleta IA se vincula directamente (`project_id`) con un `Project` de categoría `"Videos IA"`, permitiendo entrar al Estudio para revisar Tratamiento, Guion, Biblia y probar `Shots` antes de lanzar el vídeo final.

## Alcance Técnico (SOLID)

1. **Persistencia Nativa TV en `jg_filmmaker`:**
   - Migrar tablas y modelos Eloquent (`TvChannelConfig`, `TvDaySchedule`, `TvRundown`, `TvVideo`, `TvAdSlot`), incorporando clave foránea `project_id` en `tv_rundowns` y `tv_videos` apuntando a `projects` (`category = 'ai_video'`).
   - Portar `WikipediaRundownSynthesizer` dejando `script_text` limpio (sin atribución oral) y separando `visual_credit` + `sources_json`.
2. **Consola Livewire `/tv` (`App\Livewire\Tv\*`):**
   - Portar el workspace modular de TV (Parrilla, Escaletas IA, Vídeos, Ads/Fallbacks, Configuración) bajo ruta autenticada `/tv` (y soporte para modo embebido `?embed=1` sin chrome duplicado).
   - En cada Escaleta IA: botón directo **"Abrir en Estudio Filmmaker"** (`/projects/{project}`) para revisar Tratamiento, Guion, Biblia y probar imágenes de los `Shots` antes de pulsar *"Generar Vídeo Completo"*.
3. **Endpoints M2M `/api/v1/tv/rundowns`:**
   - Exponer creación de escaletas desde `jg_hub` (checkbox *"Producir Telediario TV"* en AI Today) y `jg_agente_tv` con firma HMAC-SHA256.
4. **Tests y Documentación:**
   - Tests PHPUnit de modelos, acciones TV, vinculación 1:1 `TvRundown <-> Project`, síntesis Wikipedia sin licencia oral y endpoints M2M.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
