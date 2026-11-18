# [ISSUE-39] [jg_filmmaker] fix(ai): Eliminar DeterministicFallbackEngine, suplantación de telemetría Groq en UI, cobro de tokens por PNG 1x1, WAV silencioso en caché TTS, bypass mock en producción y falso éxito en RequestLoginOtpAction

- **ID Maestro QA:** `ISSUE-39`
- **Proyecto Destino:** `javiergalvez-ia/jg_filmmaker`
- **Asignado a:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)
- **Milestone:** `QA & Anti-Fallback Hardening (Nov-Dic 2026)`
- **Labels:** `qa-audit, priority::blocker, anti-fallback, security, bug`
- **GitLab Issue Remoto:** [jg_filmmaker#35](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/35)

---

- **Severidad y Categoría:** `Blocker` | `Anti-Fallback IA` / `Seguridad` / `Fiabilidad-Bug`
- **Consenso de Auditores:** Detectado por: `argon`, `claude-sonnet`, `flash-3.8`, `hydrogen` (telemetría UI, cobro PNG 1x1, caché TTS y bug OTP por `argon`; `AppServiceProvider.php:50` por `claude-sonnet`)
- **Evidencia Exacta:**
  - `jg_filmmaker/app/Services/JgAgente/AgentHttpTransport.php:91-93, 119-183`
  - `jg_filmmaker/app/Services/JgAgente/Fallbacks/*` (`DeterministicFallbackEngine`, `DirectLlmFallbackHandler`, `ScriptFallbackHandler`, `StoryboardFallbackHandler`, `ContinuityFallbackHandler`, `CharacterFallbackHandler`)
  - `jg_filmmaker/app/Services/StoryboardService.php:112-143`
  - `jg_filmmaker/app/Livewire/Studio/Concerns/WithEditorActivityAndTrace.php:83-103` y `app/Services/Studio/StudioAiTraceBuilder.php:11-276`
  - `jg_filmmaker/app/Services/ScriptPipelineService.php:96, 132` y `app/Services/Pipeline/BibleSyncService.php:63-69, 86-107`
  - `jg_filmmaker/app/Services/TextToSpeechService.php:105-107, 145-186`
  - `jg_filmmaker/app/Providers/AppServiceProvider.php:50-62`
  - `jg_filmmaker/app/Actions/Auth/RequestLoginOtpAction.php:79-88`
- **Solución Técnica Propuesta:**
  1. En `AgentHttpTransport::callTool()`, propagar los errores HTTP 4xx/5xx y `ConnectionException` devolviendo `['success' => false, 'error' => ...]` (o lanzando excepción tipada) sin invocar `DeterministicFallbackEngine`, y prohibir el descuento de 350 tokens en `StoryboardService` si falla la generación de imagen.
  2. En `WithEditorActivityAndTrace.php:83-103` y `StudioAiTraceBuilder.php`, eliminar la sobreescritura de `$telemetry['provider']` y `$telemetry['model']` y la fabricación regex de pasos cognitivos; reemplazar `env()` por `config()`.
  3. En `AppServiceProvider.php:50-62`, exigir `JG_AGENTE_SECRET` válido en producción sin condicionarlo a `! config('services.jg_agente.mock')`, y prohibir `services.jg_agente.mock = true` cuando `$this->app->isProduction()`.
  4. En `TextToSpeechService.php:181-186`, no guardar jamás el archivo WAV silencioso de 44 bytes en `$cachedFilePath` cuando fallan los proveedores TTS.
  5. En `ScriptPipelineService.php:96, 132` y `BibleSyncService.php:63-107`, activar la extracción LLM real sin caer silenciosamente en `extractDeterministicCandidates`.
  6. En `RequestLoginOtpAction.php:79-88`, devolver `['success' => false, 'field' => 'email', 'error' => ...]` si falla el envío SMTP del código OTP.
- **Criterios de Aceptación (Checklist verificable):**
  - [ ] Una caída de `jg_agente` o de los proveedores TTS en `jg_filmmaker` se reporta explícitamente al usuario sin guardar imágenes 1x1, sin cobrar tokens, sin falsificar el proveedor en el modal de trazabilidad y sin contaminar la caché TTS con audio mudo.
  - [ ] Un fallo SMTP en `RequestLoginOtpAction` devuelve `'success' => false`.
