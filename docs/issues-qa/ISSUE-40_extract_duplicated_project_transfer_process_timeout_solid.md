# [ISSUE-40] [jg_filmmaker] refactor(solid): Extraer lógica duplicada de ProjectManager/UserDetail/UserList a Actions, reemplazar @shell_exec por Process::timeout y reducir métodos >40 líneas

- **ID Maestro QA:** `ISSUE-40`
- **Proyecto Destino:** `javiergalvez-ia/jg_filmmaker`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::high, security, dry, sonar-debt, solid`
- **GitLab Issue Remoto:** [jg_filmmaker#36](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/36)

---

- **Severidad y Categoría:** `Major` | `DRY-Duplicación` / `Seguridad` / `Complejidad-CodeSmell`
- **Consenso de Auditores:** Detectado por: `argon`, `claude-sonnet`, `hydrogen`
- **Evidencia Exacta:**
  - `jg_filmmaker/app/Livewire/Admin/ProjectManager.php:65-110, 140-175`, `UserDetail.php:61-109, 123-158` y `UserList.php:68-77`
  - `jg_filmmaker/app/Services/Import/ScriptFileExtractor.php:49-50` (`@shell_exec("pdftotext {$escapedPath} - 2>/dev/null")`)
  - 9 clases/traits con >10 métodos públicos (`ScriptPipelineService` 19, `WithEditorSceneOperations` 17, `WithEditorStoryboard` 14, `TextToSpeechService` 14, `AgentClientService` 12, `User` 12, `WithEditorPlotHoles` 11, `ScriptBlock` 11, `StoryboardService` 11) y 56 métodos >40 líneas.
- **Solución Técnica Propuesta:**
  1. Extraer `TransferProjectOwnershipAction` y `GetProjectAiTelemetrySummaryQuery` para eliminar las ~80 líneas clonadas y las consultas SQL directas en `ProjectManager`, `UserDetail` y `UserList`.
  2. En `ScriptFileExtractor.php:49-50`, reemplazar `@shell_exec` por `Process::timeout(15)->run(['pdftotext', $filePath, '-'])`.
  3. Reducir los métodos públicos de las 9 clases/traits señalados a `<= 10` y descomponer los métodos >40 líneas.
- **Criterios de Aceptación (Checklist verificable):**
  - [ ] `./vendor/bin/pint --test` y `php artisan test` en `jg_filmmaker` pasan al 100%.

---
