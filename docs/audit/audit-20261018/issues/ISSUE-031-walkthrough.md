# Walkthrough - ISSUE-031: Robustecimiento del Protocolo HMAC Inter-Servicio, Correlación y Validación de Serialización

## 1. Modificaciones Implementadas
- **`app/Services/JgAgente/AgentClientService.php`**:
  - Inclusión de cabecera `X-Key-ID` en `generateSignatureHeaders()`.
  - Serialización protegida con `JSON_THROW_ON_ERROR` en `callTool()`.
  - Inclusión del `correlation_id` en todos los mensajes de registro de incidencias HTTP o de conexión.
  - Soporte de verificación TLS en la llamada `Http::withOptions(['verify' => ...])`.
- **`config/services.php`**:
  - Declaración de `services.jg_agente.key_id` y `services.jg_agente.verify_tls`.

## 2. Verificación de Calidad
- Pint: 0 errores.
- Tests: `tests/Feature/HmacAgentClientConfigAndSqliteTest.php` pasa al 100%.
- Suite completa: 191 tests pasando en `jg_filmmaker`.
