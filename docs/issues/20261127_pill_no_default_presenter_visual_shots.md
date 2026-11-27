# Fix: Evitar personaje Presentador/HOST/LOCUTOR automático en píldoras (`pill`) para generar visuales temáticos

## Problema
En las escaletas de televisión de tipo `pill` (píldoras informativas/documentales en off), los vídeos generados muestran un presentador hablando en un plató en casi todos los shots debido a tres factores encadenados:
1. `AutoGenerateBroadcastShotsAction::createProjectAndBlock()` crea la escena inicial con slugline `INT. ESTUDIO BROADCAST - DÍA`.
2. `SyncBroadcastProjectBibleAction::execute()` llama a `seedDefaultPresenter()` cuando el proyecto no tiene personajes, creando automáticamente el personaje `Presentador` con vestuario de plató.
3. `BroadcastFramePromptBuilder::buildPrompt()` llama a `StoryboardPromptBuilder::resolveCharacterModel()`, que inyecta `Character Consistency: Depict character 'Presentador'...` y `Location & Scenography: INT. ESTUDIO BROADCAST` en los prompts de storyboard, mientras el guion Fountain introduce `HOST:` / `LOCUTOR:` en las descripciones de los shots.

## Solución Propuesta
1. **No sembrar `Presentador` ni plató de estudio en formato `pill`:**
   - En `SyncBroadcastProjectBibleAction::execute()`, no llamar a `seedDefaultPresenter()` cuando `strtolower((string) $job->format)` sea `'pill'` o `'pildora'`.
   - En `AutoGenerateBroadcastShotsAction::createProjectAndBlock()`, usar un slugline documental neutro (p. ej. `EXT. DOCUMENTAL TEMÁTICO - DÍA`) cuando el formato sea `pill` / `pildora`, y en `buildContextualizedRawText()` añadir una pauta explícita de narración en off sin presentador en cámara ni prefijos `HOST:`/`LOCUTOR:`.
2. **Sanitizar prefijos de locutor en descripciones visuales (`OralShotSanitizer` / `BroadcastFramePromptBuilder`):**
   - Limpiar prefijos de diálogo tipo `HOST:`, `LOCUTOR:`, `PRESENTADOR:`, `NARRADOR:` (y acotaciones `(V.O.)`) al construir el prompt visual en `BroadcastFramePromptBuilder::resolveDescription()` y evitar inyectar personajes por defecto en `BroadcastFramePromptBuilder::appendBibleContext()` cuando el formato del proyecto/job sea `pill` y no haya personajes reales en cámara.
3. **Tests:**
   - Añadir/actualizar tests unitarios y de integración verificando que un `BroadcastJob` con `format = 'pill'` no crea el personaje `Presentador`, no usa `INT. ESTUDIO BROADCAST - DÍA` y el prompt generado por `BroadcastFramePromptBuilder` no contiene `Presentador`, `HOST` ni `LOCUTOR`.
