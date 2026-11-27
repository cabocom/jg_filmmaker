# Feat: Tipo Biografía (`bio`), serie "Breves Biografías IA", pantalla de inicio, faldón inferior izquierdo y prompts anti-texto

## Requisitos
1. **Nuevo tipo `bio` ("Biografía") y deshabilitación de `music` en Escaletas IA:**
   - Añadir el tipo `bio` con el mismo comportamiento documental/visual sin presentador que `pill` (separando biografías de personas de píldoras de tecnología `pill`).
   - Migrar los registros existentes con `type`/`format = 'pill'` en `tv_rundowns`, `tv_videos` y `broadcast_jobs` a `'bio'` mediante una migración Laravel.
   - Deshabilitar/retirar la opción `music` del alta y edición de escaletas IA en `TvRundownsPanel` y `rundowns-panel.blade.php` (dejando `bio`, `pill`, `news`, `debate`).
   - Asignar el nombre de serie `"Breves Biografías IA"` al tipo `bio` (y nombres de serie equivalentes para `pill`, `news`, `debate`).

2. **Refuerzo anti-texto en generación de imágenes de shots:**
   - Revisar `BroadcastFramePromptBuilder` (y `VisualAestheticConfigDTO` / `StoryboardService`) para pedir explícitamente que NO se incluya ningún texto, letras, rótulos ni tipografía en las imágenes generadas de los shots.

3. **Pantalla de inicio (Intro Card) antes del vídeo (para `bio`, `pill`, `news` y `debate`):**
   - Generar una pantalla de inicio (3 segundos, 30fps CFR, con silencio AAC 48kHz) antes del cuerpo del vídeo mostrando:
     - Nombre de la serie (ej. `Breves Biografías IA` en `bio`)
     - Título del vídeo
     - Debajo: URL de Wikipedia / fuente (si existe) y atribuciones de licencia (`visual_credit`).
   - Concatenar la intro antes del vídeo principal manteniendo intacta la sincronía de `master_audio` y `master_subtitles.ass` en el cuerpo.

4. **Faldón inferior izquierdo durante el vídeo (para `bio`, `pill`, `news` y `debate`):**
   - En `ShotSubtitleGenerator` (`Style: Credit` / faldón en `.ass`), mostrar durante el vídeo un faldón abajo a la izquierda (`Alignment=1`) con el nombre de la serie + título, URL de Wikipedia / fuente y atribución de licencia, ajustando `MarginV` de los subtítulos hablados (`Style: Default`) para que no se solapen.
