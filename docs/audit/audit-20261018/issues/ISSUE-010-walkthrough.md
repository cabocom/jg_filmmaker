# Walkthrough: [ISSUE-010] Saneamiento de `AgentClientService`, observabilidad ante fallos y erradicación de bypass no autorizado

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se ha refactorizado la capa de cliente `AgentClientService` para alinearla estrictamente con el principio de arquitectura desacoplada y eliminar el bypass encubierto hacia proveedores externos:

1. **Observabilidad y Logging Estructurado sin PII:**
   - Se eliminaron los bloques `catch` silenciosos.
   - Cualquier error HTTP (status 4xx/5xx) o fallo de conexión con `jg_agente` genera una advertencia estructurada en `Log::warning` conteniendo el nombre de la herramienta, código de estado y mensaje técnico, suprimiendo estrictamente el volcado de payloads o textos del usuario.
   - Se higienizó el log de fallback en `callDirectLlm()` para no registrar el cuerpo de la respuesta (`$response->body()`).

2. **Reintentos Automáticos para Fallos Transitorios:**
   - Se incorporó `retry(2, 200)` para reintentar automáticamente solo ante fallos de conexión de red (`ConnectionException`), evitando reintentos inútiles en errores de autenticación o validación de payload.

3. **Gobernanza Estricta de Direct LLM:**
   - La llamada directa a proveedores comerciales (`callDirectLlm()`) queda terminantemente bloqueada en entornos de producción (`! app()->isProduction()`) y requiere activación explícita mediante `config('services.llm.direct_enabled', false)`.
   - Toda inferencia en producción transita a través de `jg_agente`.

4. **Trazabilidad de Degradación y Origen:**
   - Las respuestas y la telemetría ahora exponen explícitamente:
     - `source`: `'agent' | 'direct_llm' | 'deterministic_fallback'`
     - `degraded`: `true | false`
   - Se valida formalmente que `($data['success'] ?? true) === true` antes de aceptar como válida una respuesta de `jg_agente`.

---

## 2. Modificaciones en el Código

### `app/Services/JgAgente/AgentClientService.php`

```php
// Retry para fallos transitorios
$response = Http::withHeaders($headers)
    ->timeout($this->timeout)
    ->retry(2, 200, function (\Exception $exception) {
        return $exception instanceof ConnectionException;
    }, throw: false)
    ->withBody($rawBody, 'application/json')
    ->post($url);

if ($response->successful()) {
    $data = $response->json();
    if (is_array($data) && ($data['success'] ?? true) === true) {
        $data['source'] = 'agent';
        $data['degraded'] = false;
        return $data;
    }
    Log::warning("Respuesta no exitosa de jg_agente para herramienta [{$toolName}]", [
        'tool' => $toolName,
        'status' => $response->status(),
        'error' => is_array($data) ? ($data['error'] ?? 'flag success es false') : 'formato no json',
    ]);
} else {
    Log::warning("Llamada HTTP fallida a jg_agente para herramienta [{$toolName}]", [
        'tool' => $toolName,
        'status' => $response->status(),
    ]);
}

// Bloqueo estricto de direct LLM en producción
$allowDirectLlm = ! app()->isProduction()
    && ! app()->environment('testing')
    && (bool) config('services.llm.direct_enabled', false);
```

---

## 3. Pruebas Automatizadas

Se creó la suite `tests/Feature/AgentClientRobustnessTest.php` con 4 tests:
- `test_call_tool_marks_agent_source_when_successful`: comprueba etiquetado de `source: 'agent'` y `degraded: false`.
- `test_call_tool_logs_warning_and_falls_back_when_jg_agente_returns_server_error`: valida logging de advertencia y degradación determinista limpia.
- `test_call_tool_recognizes_success_false_from_agent_and_falls_back`: comprueba rechazo de respuestas con `success: false`.
- `test_direct_llm_is_strictly_disabled_in_production`: comprueba que en producción no se invoca a proveedores comerciales directos aunque esté activo el flag.

Resultado: 4/4 tests pasando con 16 aserciones.
