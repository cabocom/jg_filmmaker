# Análisis Técnico - ISSUE-014: Extracción de Telemetría IA de `user_locks` y Erradicación de Anidamiento Recursivo

## 1. Contexto y Situación Inicial
En `jg_filmmaker`, las peticiones procesadas por los agentes cognitivos registraban el objeto `$telemetry` completo dentro de la columna JSON `script_blocks.user_locks['ai_telemetry'][$stage]`.

### Deficiencias Críticas Detectadas:
1. **Anidamiento Recursivo y Crecimiento Exponencial (Payload Bloat):**
   - Al estructurar o formatear un bloque, se leía `$userLocks` (que contenía la telemetría de ejecuciones previas) y se enviaba íntegro al agente: `structureTreatment(... userLocks: $userLocks)`.
   - El agente incrustaba de nuevo el `$payload['user_locks']` en su objeto de telemetría final:
     `'user_locks' => $payload['user_locks']`
   - Tras varias regeneraciones consecutivas, el objeto `user_locks` crecía recursivamente acumulando prompts, contextos y duplicados de sí mismo en un bucle que inflaba exponencialmente el tamaño de la fila en BD (hasta megabytes).
2. **Consumo Innecesario de Tokens y Fuga de Contexto:**
   - Cada llamada al agente transportaba kilobytes de telemetría histórica previa que el modelo no necesitaba, consumiendo tokens de entrada y aumentando la latencia de red.
3. **Violación de Responsabilidad Única:**
   - La columna `user_locks` está concebida exclusivamente para la gobernanza de autoría (`pinned_terms`, `manual_diffs`, `locked_fields`, justificaciones y marcas de agujeros de guión). Mezclar telemetría de ejecución de infraestructura con reglas creativas del autor viola el principio de diseño del sistema.
4. **Distorsión en la Estimación de Costes:**
   - La estimación heurística de tokens en `AgentTelemetryService` (`strlen($inputStr)/4`) calculaba costes sobre un payload inflado por la propia telemetría recursiva anterior.

---

## 2. Plan de Solución Arquitectónica

### A. Saneamiento Estricto de `user_locks` hacia el Agente
- Implementar en `ScriptPipelineService` el helper `sanitizeUserLocksForAgent(array $userLocks): array`, extrayendo **única y exclusivamente** las claves de gobernanza de guion:
  - `pinned_terms` (términos fijos de la biblia).
  - `manual_diffs` (diferencias manuales del autor).
  - `locked_fields` (etapas protegidas).
- Ningún metadato de telemetría, prompts antiguos ni respuestas previas viajará jamás en la carga útil hacia el agente.

### B. Persistencia Dedicada en `agent_request_logs`
- Añadir la columna `meta` (JSONB nullable) a la tabla existente `agent_request_logs` mediante migración.
- En `AgentTelemetryService::record()`, persistir el detalle de telemetría estructurado (`provider`, `model`, `duration_ms`, `status`, `prompts`, `contextos`, `steps`) en `agent_request_logs.meta`.
- En `ScriptPipelineService`: Eliminar definitivamente la asignación de `$freshLocks['ai_telemetry']`.

### C. Desacoplamiento en la Inspección de IA (`ScriptEditor::openAiTrace`)
- Modificar `ScriptEditor::openAiTrace` para que consulte el último registro histórico de telemetría desde `AgentRequestLog::where('script_block_id', $block->id)->where('tool_name', $toolName)->latest()->first()`.
- Si no existe registro previo (p. ej. bloques importados o preexistentes), mantener el generador `generateFallbackTelemetry()`.

### D. Migración de Limpieza de Datos Históricos
- Crear migración que limpie la clave obsoleta `ai_telemetry` de cualquier registro de `script_blocks` que la contenga, dejando el JSON `user_locks` ligero y canónico.

---

## 3. Estrategia de Pruebas
1. **Prueba de Payload Limpio al Agente:**
   - Verificar que al invocar `transitionToStructured` o `transitionToScreenplay`, el parámetro `userLocks` recibido por el agente solo contiene `pinned_terms`, `manual_diffs` y `locked_fields`, sin rastros de telemetría ni claves espurias.
2. **Prueba de Inexistencia de `ai_telemetry` en `user_locks`:**
   - Tras ejecutar el pipeline, asegurar que `$block->fresh()->user_locks` no contiene la clave `ai_telemetry`.
3. **Prueba de Trazabilidad en `openAiTrace`:**
   - Verificar que `openAiTrace` recupera los metadatos desde `agent_request_logs` sin apoyarse en `user_locks`.
