# [REFACTOR-v2.0] Refactorización Estructural SOLID y Descomposición de Monolitos (`jg_filmmaker`)

- **Estado:** Completado al 100% (Fases 1, 2 y 3 cerradas)
- **Fecha:** 2026-11-09 (Actualizado: 2026-11-12)
- **Proyecto:** `jg_filmmaker`
- **GitLab Issue:** [jg_filmmaker#33](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/33) (Relacionado con Auditoría [jg_filmmaker#32](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/issues/32))
- **Asignado a:** Javier Gálvez (`@cabocom`)
- **Documento Maestro:** [`docs/refactor.md`](../../../docs/refactor.md)

---

## 1. Contexto y Objetivo (Arquitectura v2.0 SOLID)

Este issue planifica la refactorización estructural v2.0 de `jg_filmmaker` para cumplir estrictamente las directrices de programación estructurada y principios **SOLID** definidos en `AGENTS.md` (Sección 8) y en `docs/refactor.md` (límite máximo de **300 LOC por clase**, **10 métodos públicos** y **40 LOC por método**).

## 2. Diagnóstico Real de Ficheros Monolíticos (`>300 LOC`)

Actualmente `jg_filmmaker` contaba con **12 ficheros por encima del umbral de 300 líneas**, todos ellos resueltos y descompuestos:
1. `resources/views/livewire/studio/script-editor.blade.php` (**3.663 LOC** -> **REDUCIDO a 23 LOC en Fase 3**)
2. `app/Livewire/Studio/ScriptEditor.php` (**3.284 LOC** -> Reducido mediante extracción de 9 Actions en Fase 2)
3. `app/Services/JgAgente/AgentClientService.php` (**1.861 LOC** -> **REDUCIDO a 227 LOC en Fase 1**)
4. `app/Services/TextToSpeechService.php` (**974 LOC** -> **REDUCIDO a 227 LOC en Fase 3**)
5. `resources/views/livewire/studio/context-bible-drawer.blade.php` (**897 LOC** -> **REDUCIDO a 85 LOC en Fase 3**)
6. `app/Services/ScriptPipelineService.php` (**883 LOC** -> **REDUCIDO a 251 LOC en Fase 3**)
7. `app/Livewire/Studio/ContextBibleDrawer.php` (**618 LOC** -> **REDUCIDO a 151 LOC** vía traits modulares `WithBibleEntities` y `WithBibleAudits`)
8. `app/Services/StoryboardService.php` (**415 LOC** -> **REDUCIDO a 219 LOC**)
9. `resources/views/livewire/projects/project-list.blade.php` (**411 LOC** -> **REDUCIDO a 5 LOC** vía parciales `project-grid`, `project-modals`, `project-import-modal`)
10. `resources/views/layouts/app.blade.php` (**396 LOC** -> **REDUCIDO a 131 LOC** vía parciales `header` y `scripts`)
11. `app/Services/ScriptImportService.php` (**380 LOC** -> **REDUCIDO a 129 LOC**)
12. `app/Livewire/Auth/Login.php` (**309 LOC** -> **REDUCIDO a 98 LOC** vía `RequestLoginOtpAction` y `VerifyLoginOtpAction`)
13. `app/Livewire/Studio/ScriptEditor.php` (**2.603 LOC** -> **REDUCIDO a 107 LOC** vía 12 traits enfocados en `Concerns/` y `StudioAiTraceBuilder`)
14. `resources/views/livewire/studio/partials/editor/token-quota-modals.blade.php` (**672 LOC** -> **REDUCIDO a 6 LOC** vía submodales dedicados)
15. `resources/views/livewire/studio/partials/editor/block-detail-drawer.blade.php` (**632 LOC** -> **REDUCIDO a 27 LOC** vía subcomponentes de drawer)
16. `resources/views/livewire/studio/partials/editor/scenes-flow.blade.php` (**472 LOC** -> **REDUCIDO a 84 LOC** vía `scene-card-header` y `scene-card-blocks`)

## 3. Plan de Refactorización Estructural (Patrón Strangler Fig / Cero Roturas)

- [x] **Fase 1 (`AgentClientService.php` -> Clientes de Dominio + Transporte):**
  - Extraer `HmacSigner`, `AgentHttpTransport` y `TelemetryRecorder`.
  - Dividir llamadas por dominio en `ScriptAgentClient`, `StoryboardAgentClient`, `ContinuityAgentClient` y `CharacterAgentClient` usando DTOs inmutables (`readonly class`).
  - Desacoplar heurísticas y motores de fallback en clases especializadas (`app/Services/JgAgente/Fallbacks/`).
  - Mantener `AgentClientService` como fachada 100% compatible hacia atrás (<230 LOC).
- [x] **Fase 2 (`ScriptEditor.php` y `ContextBibleDrawer.php` -> Patrón Action y Concerns):**
  - Extraer `ScriptEditor.php` (2.603 LOC a 107 LOC) mediante 12 traits modulares en `app/Livewire/Studio/Concerns/` y `StudioAiTraceBuilder` (277 LOC).
  - Extraer `ContextBibleDrawer.php` (448 LOC a 151 LOC) mediante `WithBibleEntities.php` (187 LOC) y `WithBibleAudits.php` (137 LOC).
  - Extraer `Login.php` (309 LOC a 98 LOC) mediante `RequestLoginOtpAction.php` y `VerifyLoginOtpAction.php`.
  - Mantener los métodos públicos de `wire:click` en Livewire como *thin facades* de 1–3 líneas para no romper vistas Blade ni los tests `Livewire::test()`.
- [x] **Fase 3 (Vistas Blade y Servicios Secundarios):**
  - Dividir `script-editor.blade.php` (de 3.663 a 23 LOC) y `context-bible-drawer.blade.php` (de 897 a 85 LOC) en parciales semánticos (`partials/editor/` y `partials/bible/`).
  - Modularizar subcomponentes Blade superiores a 300 LOC: `token-quota-modals.blade.php` (672 a 6 LOC), `block-detail-drawer.blade.php` (632 a 27 LOC), `scenes-flow.blade.php` (472 a 84 LOC), `crud-modals.blade.php` (461 a 5 LOC), `characters-tab.blade.php` (428 a 4 LOC), `storyboard-modals.blade.php` (341 a 2 LOC), `header-assets.blade.php` (320 a 2 LOC), `app.blade.php` (396 a 131 LOC) y `project-list.blade.php` (411 a 5 LOC).
  - Refactorizar `TextToSpeechService.php` (de 973 a 227 LOC) bajo el patrón Strategy (`TtsProviderInterface`, `GoogleAiTtsProvider`, `SpeachesTtsProvider`, `ScreenplaySegmenter`, `CharacterVoiceMapper`, `TtsManifestService`).
  - Dividir `ScriptPipelineService.php` (de 882 a 251 LOC) en `BibleSyncService`, `SluglineSyncService`, `ShotsBreakdownService`, `BatchPipelineProcessor` y `UserLockManager`.
  - Dividir `StoryboardService.php` (de 415 a 219 LOC) en `StoryboardPromptBuilder` y `StoryboardImageProcessor`.
  - Dividir `ScriptImportService.php` (de 380 a 129 LOC) en `ScriptFileExtractor` y `ScreenplayParser`.

## 4. Estimación con Gemini Flash y Criterios de Aceptación

- **Esfuerzo completado:** 3 fases ejecutadas con rigor absoluto.
- **Criterios de cierre:**
  - [x] Ninguna clase de negocio/servicio o componente Livewire supera las 300 LOC (0 archivos > 300 LOC en `app/`).
  - [x] Ninguna vista Blade de producción supera las 300 LOC (0 archivos > 300 LOC en `resources/views/`).
  - [x] 100% de métodos por debajo de 40 LOC y <= 10 métodos públicos por clase.
  - [x] 100% de tests en verde (`php artisan test`: 239/239 pasados con 1.166 assertions) y `./vendor/bin/pint --test` limpio sin infracciones.
