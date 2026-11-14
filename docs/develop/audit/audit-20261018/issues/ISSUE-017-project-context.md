# Contexto del Proyecto tras ISSUE-017

## Estado del Sistema de Control de Cuotas y Telemetría de IA

Con la consolidación de `ISSUE-017`, `jg_filmmaker` implementa una gobernanza estricta e infranqueable de consumo de modelos de lenguaje:

1. **Atribución Indeleble de Consumo:**
   - La tabla `agent_request_logs` almacena la clave foránea `user_id` junto a `project_id`.
   - Las operaciones de transferencia de proyectos (`transferProject`) no alteran el cómputo de tokens históricos de los usuarios emisores ni trasladan costes indebidos a los receptores.
   - Las consultas de saldo diario en `User::getTokensUsedToday()` se apoyan en el índice compuesto `['user_id', 'created_at']`, minimizando la sobrecarga computacional en cada render del layout.

2. **Gobernanza Centralizada en el Pipeline:**
   - El método `assertUserQuotaAvailable()` en `ScriptPipelineService` actúa como guardián canónico para todas las transformaciones asistidas (`transitionToStructured`, `transitionToScreenplay`, `generateShotsBreakdown`, `analyzePlotHoles`).
   - El fallo de cuota se comunica mediante la excepción estándar `QuotaExceededException` (HTTP 429), impidiendo el envío de solicitudes al proveedor o microservicio satélite si no existe saldo diario suficiente.

3. **Auditoría Resiliente y Chunking por Etapas:**
   - En `ScriptEditor`, la auditoría dramática de agujeros de guión evalúa el saldo antes de solicitar la confirmación del usuario y re-evalúa la disponibilidad entre cada consolidado (`raw` -> `structured` -> `screenplay`), evitando el procesamiento a ciegas de proyectos masivos.
