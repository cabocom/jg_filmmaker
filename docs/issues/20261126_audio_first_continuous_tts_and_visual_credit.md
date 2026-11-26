# Issue: Arquitectura Audio-First: Locución continua de bloque, agrupación por frases y atribución visual

- **Fase:** Fase 3 — Motor Audio-First, Frases Completas y Atribución Visual
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 6 – 7 h (Media-Alta)
- **Estado:** Cerrado (Commit `36f6bd8`, GitLab `jg_filmmaker#41`)

## Contexto y Motivación

Actualmente `AutoGenerateBroadcastShotsAction::assignOralTextFromRawScript()` divide el texto por palabras individuales cuando hay más planos que frases, provocando fragmentos de 2–3 palabras sintetizados en llamadas TTS separadas. Además, `estimated_seconds` se fuerza a un mínimo de `12s` desincronizando los subtítulos, no se propaga `tts_voice`, y la coletilla `"Fuente: Wikipedia bajo licencia CC BY-SA 4.0."` se locuta por voz en lugar de mostrarse visualmente.

## Alcance Técnico (SOLID)

1. **Agrupación por Oraciones Completas (`SentenceBasedShotDistributor`):**
   - Garantizar que ningún plano reciba fragmentos de palabras sueltas: el número efectivo de planos activos nunca supera el número de frases completas (`1–2 frases completas` por plano).
   - Propagar `tts_voice` y `tts_provider` en `BroadcastJobRequestDTO` y `StoreBroadcastJobRequest`.
2. **Síntesis de Audio Maestro Continuo (*Audio-First* — `SynthesizeBroadcastMasterAudioAction`):**
   - Sintetizar todo el guion oral limpio de un tirón en un único `master_audio.wav` (prosodia natural, 1 sola llamada API TTS).
   - Medir la duración real con `ffprobe` y calcular la ventana temporal exacta `[start_seconds, duration_seconds]` de cada `Shot` de forma proporcional al peso textual de sus frases.
3. **Atribución Visual Exclusiva (Cero Locución de Licencias):**
   - Añadir campo `visual_credit` en `BroadcastJobRequestDTO` + saneamiento defensivo en `OralShotSanitizer` para eliminar coletillas de licencia (`CC BY-SA 4.0`) del texto oral.
   - Ampliar `ShotSubtitleGenerator` para generar un `.ass` global sincronizado al audio maestro con una segunda capa `Style: Credit` (faldón/esquina discreta) que muestre la atribución visualmente sin locutarla jamás.
4. **Tests y Documentación:**
   - Tests PHPUnit para distribución por frases, cálculo exacto de ventanas temporales, respeto de `tts_voice` y capa `Style: Credit` en `.ass`.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
