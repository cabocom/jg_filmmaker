# [REFACTOR-v2.0] Refactorización Estructural SOLID y Descomposición de Monolitos (`jg_filmmaker`)

- **Estado:** En Progreso (Fase 1 completada)
- **Fecha:** 2026-11-09 (Actualizado: 2026-11-12)
- **Proyecto:** `jg_filmmaker`
- **GitLab Issue:** [jg_filmmaker#33](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/33) (Relacionado con Auditoría [jg_filmmaker#32](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/issues/32))
- **Asignado a:** Javier Gálvez (`@cabocom`)
- **Documento Maestro:** [`docs/refactor.md`](../../../docs/refactor.md)

---

## 1. Contexto y Objetivo (Arquitectura v2.0 SOLID)

Este issue planifica la refactorización estructural v2.0 de `jg_filmmaker` para cumplir estrictamente las directrices de programación estructurada y principios **SOLID** definidos en `AGENTS.md` (Sección 8) y en `docs/refactor.md` (límite máximo de **300 LOC por clase**, **10 métodos públicos** y **40 LOC por método**).

## 2. Diagnóstico Real de Ficheros Monolíticos (`>300 LOC`)

Actualmente `jg_filmmaker` contaba con **12 ficheros por encima del umbral de 300 líneas**:
1. `resources/views/livewire/studio/script-editor.blade.php` (**3.663 LOC**)
2. `app/Livewire/Studio/ScriptEditor.php` (**3.284 LOC**, 114 métodos)
3. `app/Services/JgAgente/AgentClientService.php` (**1.861 LOC** -> **REDUCIDO a 227 LOC en Fase 1**)
4. `app/Services/TextToSpeechService.php` (**974 LOC**, 20 métodos)
5. `resources/views/livewire/studio/context-bible-drawer.blade.php` (**897 LOC**)
6. `app/Services/ScriptPipelineService.php` (**883 LOC**, 18 métodos)
7. `app/Livewire/Studio/ContextBibleDrawer.php` (**618 LOC**, 24 métodos)
8. `app/Services/StoryboardService.php` (**415 LOC**)
9. `resources/views/livewire/projects/project-list.blade.php` (**411 LOC**)
10. `resources/views/layouts/app.blade.php` (**396 LOC**)
11. `app/Services/ScriptImportService.php` (**380 LOC**)
12. `app/Livewire/Auth/Login.php` (**309 LOC**)

## 3. Plan de Refactorización Estructural (Patrón Strangler Fig / Cero Roturas)

- [x] **Fase 1 (`AgentClientService.php` -> Clientes de Dominio + Transporte):**
  - Extraer `HmacSigner`, `AgentHttpTransport` y `TelemetryRecorder`.
  - Dividir llamadas por dominio en `ScriptAgentClient`, `StoryboardAgentClient`, `ContinuityAgentClient` y `CharacterAgentClient` usando DTOs inmutables (`readonly class`).
  - Desacoplar heurísticas y motores de fallback en clases especializadas (`app/Services/JgAgente/Fallbacks/`).
  - Mantener `AgentClientService` como fachada 100% compatible hacia atrás (<230 LOC).
- [ ] **Fase 2 (`ScriptEditor.php` y `ContextBibleDrawer.php` -> Patrón Action):**
  - Extraer la lógica de negocio a clases `final readonly class <CasoDeUso>Action` en `app/Actions/Studio/` (`GenerateSceneBreakdownAction`, `AssembleRoughCutAction`, `ExportScriptAction`, `AnalyzePlotHolesAction`, etc.).
  - Mantener los métodos públicos de `wire:click` en Livewire como *thin facades* de 1–3 líneas para no romper vistas Blade ni los tests `Livewire::test()`.
- [ ] **Fase 3 (Vistas Blade y Servicios Secundarios):**
  - Dividir `script-editor.blade.php` y `context-bible-drawer.blade.php` en parciales semánticos (`modals/`, `panels/`, `toolbar/`).
  - Refactorizar `TextToSpeechService.php` bajo el patrón Strategy (`TtsProviderInterface`) y dividir `ScriptPipelineService.php` y `ScriptImportService.php`.

## 4. Estimación con Gemini Flash y Criterios de Aceptación

- **Esfuerzo estimado:** 8 – 10 sesiones (~4,5 M – 6,0 M tokens Gemini Flash, ~3 – 4 horas).
- **Criterios de cierre:**
  - [ ] Ninguna clase de negocio/servicio supera las 300 LOC.
  - [x] 100% de tests en verde (`php artisan test`: 239/239 pasados) y `./vendor/bin/pint --test` limpio.
