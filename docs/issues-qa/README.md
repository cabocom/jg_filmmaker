# Backlog QA & Seguridad Consolidado — `jg_filmmaker`

**Repositorio GitLab:** `https://gitlab.com/javiergalvez-ia/jg_filmmaker`  
**Responsable Asignado:** `cabocom` / Javier Gálvez (`assignee_ids: [553306]`)  
**Total de Issues en `jg_filmmaker`:** **2**

## Índice de Issues de Remediación

| ID | Severidad | Categoría | Título | Consenso Modelos | Archivo Local | Issue GitLab |
| :--- | :---: | :--- | :--- | :--- | :--- | :--- |
| `ISSUE-39` | **Blocker** | `Anti-Fallback IA / Seguridad / Fiabilidad-Bug` | fix(ai): Eliminar DeterministicFallbackEngine, suplantación de telemetría Groq en UI, cobro de tokens por PNG 1x1, WAV silencioso en caché TTS, bypass mock en producción y falso éxito en RequestLoginOtpAction | Detectado por: `argon`, `claude-sonnet`, `flash-3.8`, `hydrogen` (telemetría UI, cobro PNG 1x1, caché TTS y bug OTP por `argon`; `AppServiceProvider.php:50` por `claude-sonnet`) | [ISSUE-39_remove_deterministic_fallback_engine_spoofed_trace_tts_wav.md](./ISSUE-39_remove_deterministic_fallback_engine_spoofed_trace_tts_wav.md) | [#35](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/35) |
| `ISSUE-40` | **Major** | `DRY-Duplicación / Seguridad / Complejidad-CodeSmell` | refactor(solid): Extraer lógica duplicada de ProjectManager/UserDetail/UserList a Actions, reemplazar @shell_exec por Process::timeout y reducir métodos >40 líneas | Detectado por: `argon`, `claude-sonnet`, `hydrogen` | [ISSUE-40_extract_duplicated_project_transfer_process_timeout_solid.md](./ISSUE-40_extract_duplicated_project_transfer_process_timeout_solid.md) | [#36](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/36) |
