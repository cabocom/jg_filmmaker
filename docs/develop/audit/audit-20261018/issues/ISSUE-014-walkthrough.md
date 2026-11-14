# Walkthrough Técnico - ISSUE-014: Extracción de Telemetría IA de `user_locks` a `agent_request_logs`

## 1. Resumen de Cambios
Se ha erradicado el anidamiento recursivo de telemetría y prompts en la columna `script_blocks.user_locks`. La telemetría técnica completa ahora se persiste de forma normalizada en la tabla dedicada `agent_request_logs.meta`, y las cargas útiles enviadas al motor de IA se sanitizan para incluir estrictamente directrices de autoría.

## 2. Componentes Modificados

### A. Base de Datos y Modelo `AgentRequestLog`
- **Migración (`2026_10_11_000004_add_meta_to_agent_request_logs_table.php`):**
  - Añadida columna `meta` (JSONB nullable) a la tabla `agent_request_logs`.
- **Migración (`2026_10_11_000005_clean_ai_telemetry_from_script_blocks_table.php`):**
  - Recorre los bloques existentes y elimina la clave obsoleta `ai_telemetry` de `user_locks`.
- **Modelo `AgentRequestLog` (`app/Models/AgentRequestLog.php`):**
  - Añadido `meta` a `$fillable` y `$casts` (`'meta' => 'array'`).

### B. Servicio de Telemetría (`AgentTelemetryService.php`)
- En `record()`:
  - Sanitiza el objeto `$telemetry` (eliminando cualquier rastro de `ai_telemetry` dentro de `user_locks`).
  - Persiste el objeto estructurado directamente en la columna `agent_request_logs.meta`.

### C. Pipeline y Gobernanza de Candados (`ScriptPipelineService.php`)
- **Helper `sanitizeUserLocksForAgent(array $userLocks): array`:**
  - Extrae rigurosamente solo `pinned_terms`, `manual_diffs` y `locked_fields`.
  - Asegura que ninguna clave de telemetría previa viaje hacia el agente en `structureTreatment` ni `formatScreenplay`.
- **Eliminación de `$freshLocks['ai_telemetry']`:**
  - En `transitionToStructured`, `transitionToScreenplay` y `generateShotsBreakdown`, se suprimió la escritura de telemetría dentro de `user_locks`.
  - La columna `user_locks` queda reservada exclusivamente a las preferencias del usuario.

### D. Agente Cognitivo (`AgentClientService.php`)
- En las asignaciones de `lastTelemetry` (tanto en motor heurístico como en llamada a LLM directo), se incorporó el método `sanitizeTelemetryLocks(array $locks): array`, garantizando que el snapshot de telemetría no capture de forma recursiva sub-objetos históricos.

### E. Livewire Studio (`ScriptEditor.php`)
- En `openAiTrace(string $blockId, string $stage)`:
  - La consulta de telemetría ahora lee de `AgentRequestLog::where('script_block_id', $block->id)->where('tool_name', $toolName)->latest()->first()->meta`.
  - Desacoplamiento total de `user_locks`.
- En `generateFallbackTelemetry()`:
  - Limpieza de `user_locks` a sus claves canónicas.

## 3. Pruebas Automatizadas
- Creado `tests/Feature/UserLocksTelemetryExtractionTest.php`:
  1. `test_pipeline_sanitizes_user_locks_sent_to_agent_client_omitting_telemetry`: Verifica que `structureTreatment` recibe solo las claves de autoría y no telemetría pesada.
  2. `test_agent_telemetry_persisted_in_agent_request_logs_table_meta`: Verifica que `AgentRequestLog` almacena la telemetría en `meta` sin bucle recursivo.
  3. `test_open_ai_trace_reads_telemetry_from_agent_request_logs`: Verifica que el modal de inspección IA recupera los datos desde la tabla de logs.

## 4. Verificación
- Suite de pruebas de `jg_filmmaker`: 124 tests pasados (635 aserciones).
- Linter Pint: 0 errores.
- Script global `./scripts/check.sh --cicd -p jg_filmmaker`: Exitoso.
