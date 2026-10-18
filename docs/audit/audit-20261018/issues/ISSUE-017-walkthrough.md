# Walkthrough - ISSUE-017: Control Centralizado de Cuota Diaria de Tokens de IA y Erradicación de Check-Then-Act

## Resumen de la Implementación

En `ISSUE-017`, se resolvió la omisión crítica del control de cuota en las operaciones de IA del pipeline y la fuga de imputación de consumo tras transferencias de proyectos:

1. **Excepción de Cuota Canónica (`QuotaExceededException`):**
   - Se creó `app/Exceptions/QuotaExceededException.php` con código HTTP 429 y métodos de inspección `getRequiredTokens()` y `getAvailableTokens()`.

2. **Imputación de Consumo a Nivel de Usuario (`agent_request_logs.user_id`):**
   - Migración `2026_10_11_000006_add_user_id_to_agent_request_logs_table.php` que añade la columna `user_id` (foreignUuid referenciando a `users`), índice compuesto `['user_id', 'created_at']` y poblado retroactivo desde `projects.user_id`.
   - Relaciones `AgentRequestLog::user()` y `User::agentRequestLogs()`.
   - `AgentTelemetryService::record()` acepta `?string $userId = null` y resuelve el usuario del proyecto automáticamente si no se suministra.

3. **Cómputo Indeleble contra Elusiones en Transferencias:**
   - Se optimizó `User::getTokensUsedToday()` para consultar de forma indexada `agent_request_logs.user_id = $this->id` con retrocompatibilidad a `projects.user_id`.
   - Cuando un proyecto se transfiere a un nuevo usuario, el histórico de tokens consumidos previamente se mantiene inalterado y computado a nombre del autor original.

4. **Validación Centralizada de Cuota en el Pipeline:**
   - En `ScriptPipelineService`, se implementó `assertUserQuotaAvailable(?Project $project, int $estimatedTokens = 100)`.
   - Se integró la verificación con estimación proporcional al tamaño del texto en:
     - `transitionToStructured()`
     - `transitionToScreenplay()`
     - `generateShotsBreakdown()`

5. **Protección y Feedback Inmediato en `ScriptEditor`:**
   - Se envolvieron las invocaciones a `transitionToStructured`, `transitionToScreenplay` y `generateShotsBreakdown` en bloques `try / catch (QuotaExceededException $e)` para emitir notificaciones flash con el límite del usuario y abortar la operación sin alterar el estado.
   - En `promptAnalyzePlotHoles()`, se valida previamente que el saldo disponible cubra la estimación total del proyecto antes de abrir el modal de confirmación.
   - En `analyzePlotHoles()`, se valida la cuota antes de iniciar y se re-chequea antes de auditar cada una de las fases (`raw`, `structured`, `screenplay`), interrumpiendo limpiamente si el cupo se agota a mitad del proceso.

## Archivos Modificados y Creados

- `app/Exceptions/QuotaExceededException.php`: Clase de excepción tipada para cuota excedida.
- `database/migrations/2026_10_11_000006_add_user_id_to_agent_request_logs_table.php`: Migración de base de datos.
- `app/Models/AgentRequestLog.php`: Fillable y relación `user()`.
- `app/Models/User.php`: Relación `agentRequestLogs()` y optimización de `getTokensUsedToday()`.
- `app/Services/AgentTelemetryService.php`: Soporte para asignación explícita e inferida de `user_id`.
- `app/Services/ScriptPipelineService.php`: `assertUserQuotaAvailable()` y comprobaciones previas en cada etapa.
- `app/Livewire/Studio/ScriptEditor.php`: Captura de `QuotaExceededException` y comprobaciones proporcionales en auditoría.
- `database/factories/AgentRequestLogFactory.php`: Factory para generación de registros de telemetría.
- `tests/Feature/TokenQuotaEnforcementTest.php`: Suite de 6 tests de verificación exhaustiva.

## Verificación de Calidad

- **Linters:** `./vendor/bin/pint --test` pasado con 0 advertencias.
- **Tests Unitarios y de Integración:** `php artisan test --filter=TokenQuotaEnforcementTest` (6 tests, 18 aserciones, 100% pasando).
- **Suite Completa:** `php artisan test` (135 tests, 668 aserciones, 100% pasando).
