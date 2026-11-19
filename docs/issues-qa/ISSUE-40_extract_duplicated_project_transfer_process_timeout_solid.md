# [ISSUE-40] [jg_filmmaker] refactor(solid): Extraer lógica duplicada de ProjectManager/UserDetail/UserList a Actions, reemplazar @shell_exec por Process::timeout y reducir métodos >40 líneas

- **ID Maestro QA:** `ISSUE-40`
- **Proyecto Destino:** `javiergalvez-ia/jg_filmmaker`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::high, security, dry, sonar-debt, solid`
- **GitLab Issue Remoto:** [jg_filmmaker#36](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/36)
- **Estado:** `RESUELTO / CERRADO`

---

- **Severidad y Categoría:** `Major` | `DRY-Duplicación` / `Seguridad` / `Complejidad-CodeSmell`
- **Consenso de Auditores:** Detectado por: `argon`, `claude-sonnet`, `hydrogen`
- **Evidencia Exacta Original:**
  - `jg_filmmaker/app/Livewire/Admin/ProjectManager.php:65-110, 140-175`, `UserDetail.php:61-109, 123-158` y `UserList.php:68-77`
  - `jg_filmmaker/app/Services/Import/ScriptFileExtractor.php:49-50` (`@shell_exec("pdftotext {$escapedPath} - 2>/dev/null")`)
  - 9 clases/traits con >10 métodos públicos (`ScriptPipelineService` 19, `WithEditorSceneOperations` 17, `WithEditorStoryboard` 14, `TextToSpeechService` 14, `AgentClientService` 12, `User` 12, `WithEditorPlotHoles` 11, `ScriptBlock` 11, `StoryboardService` 11) y 56 métodos >40 líneas.

- **Solución Técnica Implementada:**
  1. **Acciones y Queries Administrativas Desacopladas:**
     - Creado `app/Actions/Admin/TransferProjectOwnershipAction.php` para encapsular la reasignación de proyectos de forma transaccional, validando usuario de destino, reseteando cachés y registrando auditoría.
     - Creado `app/Queries/Admin/GetProjectAiTelemetrySummaryQuery.php` para consolidar las consultas agregadas y métricas de telemetría IA de proyectos.
     - Refactorizados `ProjectManager.php`, `UserDetail.php` y `UserList.php` para delegar en la Action y la Query, eliminando las consultas SQL duplicadas y reduciendo ~80 líneas de código clonado.
  2. **Eliminación Segura de `@shell_exec`:**
     - En `ScriptFileExtractor.php:49-50`, sustituida la llamada bloqueante de shell por `Process::timeout(15)->run(['pdftotext', $filePath, '-'])`, con validación de código de salida (`successful()`) y manejo de excepciones por timeout.
  3. **Descomposición SOLID y Reducción a $\le 10$ Métodos Públicos:**
     - `ScriptPipelineService`: Reducido a 9 métodos extrayendo `WithBatchPipelineProcessing` y `WithBibleSyncDelegation`.
     - `TextToSpeechService`: Reducido a 7 métodos extrayendo `WithTtsManifestDelegation`.
     - `StoryboardService`: Reducido a 6 métodos eliminando métodos redundantes.
     - `AgentClientService`: Reducido a 10 métodos extrayendo `WithAgentClientTextParsing`.
     - `WithEditorPlotHoles`: Reducido a 8 métodos extrayendo `WithEditorPlotHoleJustification`.
     - `WithEditorStoryboard`: Reducido a 6 métodos extrayendo `WithEditorStoryboardModalEvents`.
     - `WithEditorSceneOperations`: Reducido a 9 métodos extrayendo `WithEditorSceneModals`.
     - `User`: Reducido a 6 métodos extrayendo `HasUserTokenQuota`.
     - `ScriptBlock`: Reducido a 6 métodos extrayendo `HasScriptBlockPlotHoles`.
  4. **Reducción de Métodos Monolíticos:**
     - Descompuestos métodos extensos de extracción y renderizado para garantizar que se ajusten a los umbrales de mantenibilidad (<40 líneas).

- **Criterios de Aceptación (Checklist verificable):**
  - [x] `./vendor/bin/pint --test` pasa con 0 infracciones.
  - [x] `php artisan test` pasa al 100% (319 tests, 1.479 assertions).
  - [x] Cachés purgadas (`view:clear`, `cache:clear`, `config:clear`, `route:clear`) y contenedor Docker `jg_filmmaker` reiniciado.

---
