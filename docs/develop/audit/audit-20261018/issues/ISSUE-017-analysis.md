# Análisis - ISSUE-017: Control Centralizado de Cuota Diaria de Tokens de IA y Erradicación de Check-Then-Act

## 1. Diagnóstico de la Situación Inicial

- **Verificación selectiva y umbral simbólico:** La comprobación `hasDailyTokensAvailable()` únicamente se ejecutaba dentro de `ScriptEditor::analyzePlotHoles()` exigiendo un umbral de solo 10 tokens (`hasDailyTokensAvailable(10)`), a pesar de que la auditoría procesa todo el texto del proyecto en tres pasadas consecutivas (`raw`, `structured`, `screenplay`) pudiendo consumir miles de tokens.
- **Vulnerabilidad de cuota ilimitada en el pipeline:** Las acciones más frecuentes y de mayor coste computacional —`structureBlock` (tratamiento), `formatBlockToScreenplay` (guion literario) y `breakdownBlockShots` (desglose de planos)— carecen por completo de verificación de cuota diaria. Un usuario con cupo consumido o superado puede continuar llamando a la IA indefinidamente.
- **Fuga de imputación tras transferencia de proyectos:** La tabla `agent_request_logs` solo registraba `project_id`. Al transferir un proyecto a otro usuario (`transferProject`), la consulta con `JOIN projects ON projects.user_id` trasladaba todo el historial de consumo al nuevo dueño, reseteando artificialmente el cupo del usuario emisor (elusión de cuota).
- **Falta de registro en rutas de error:** Si una llamada fallaba o era abortada tras el envío al proveedor, no se imputaba ningún token estimado a la cuota a pesar del gasto realizado.

## 2. Plan de Solución

1. **Excepción canónica `QuotaExceededException`:**
   - Crear `app/Exceptions/QuotaExceededException.php` para tipar específicamente los rechazos por límite de cuota agotada.

2. **Migración de base de datos (`agent_request_logs.user_id`):**
   - Añadir columna `user_id` (foreignUuid hacia `users`) e índice `['user_id', 'created_at']` a `agent_request_logs`.
   - Rellenar `user_id` a partir de `projects.user_id` para registros preexistentes.

3. **Imputación y consulta precisa en `User.php` y `AgentTelemetryService.php`:**
   - En `AgentTelemetryService::record()`, aceptar `?string $userId = null` e inferirlo de `$project->user_id` si no se pasa explícitamente.
   - En `User::getTokensUsedToday()`, consultar con índice directo sobre `agent_request_logs.user_id` (con fallback a `projects.user_id` para retrocompatibilidad).

4. **Garantía y verificación centralizada en `ScriptPipelineService.php`:**
   - Añadir método `assertUserQuotaAvailable(Project $project, int $estimatedTokens = 100): void`.
   - Invocarlo al inicio de `transitionToStructured()`, `transitionToScreenplay()`, `generateShotsBreakdown()` y antes de cada iteración de `analyzePlotHoles()`.
   - Lanzar `QuotaExceededException` si la cuota está agotada.

5. **Protección y feedback en `ScriptEditor.php`:**
   - Capturar `QuotaExceededException` en `structureBlock`, `formatBlockToScreenplay`, `breakdownBlockShots` y `analyzePlotHoles`, mostrando el mensaje flash de error de cupo alcanzado y bloqueando la ejecución.
   - En `promptAnalyzePlotHoles`, validar que el usuario posea al menos los tokens estimados calculados antes de permitir la confirmación.

6. **Tests y verificación:**
   - Crear `tests/Feature/TokenQuotaEnforcementTest.php` validando:
     - Bloqueo en `structureBlock`, `formatBlockToScreenplay`, `breakdownBlockShots` cuando el cupo está agotado.
     - Estimación y bloqueo proporcional en `analyzePlotHoles`.
     - Imputación a `user_id` en `agent_request_logs`.
     - Intactitud del consumo del usuario tras transferencia de proyecto.
