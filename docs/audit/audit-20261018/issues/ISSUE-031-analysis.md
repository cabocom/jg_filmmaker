# Análisis Técnico - ISSUE-031: Robustecimiento del Protocolo HMAC Inter-Servicio, Correlación y Validación de Serialización

## 1. Contexto y Diagnóstico
El servicio de cliente de agente (`AgentClientService`) emitía peticiones firmadas con HMAC-SHA256, pero presentaba las siguientes deficiencias:
1. **Falta de soporte de clave/rotación:** No se enviaba un identificador de clave (`X-Key-ID`) que permitiese al receptor asociar la firma a una versión específica del secreto.
2. **Serialización permisiva:** Se utilizaba `json_encode(...) ?: '{}'`, lo que provocaba que en caso de fallo de codificación de caracteres se enviara y firmara un cuerpo vacío válido `{}` en lugar de fallar de inmediato.
3. **Falta de correlación en logs:** `X-Correlation-ID` no se vinculaba sistemáticamente en las trazas de advertencia y error del cliente.
4. **Validación TLS:** Sin opción explícita para verificar TLS en la comunicación interna.

## 2. Solución Diseñada
1. **Identificador de clave y rotación:** Incorporación de la cabecera canónica `X-Key-ID` gestionada mediante `config('services.jg_agente.key_id')`.
2. **Serialización fail-fast:** Uso de `JSON_THROW_ON_ERROR` en `json_encode()`. Si el payload es corrupto o no serializable, se interrumpe la ejecución con `InvalidArgumentException`.
3. **Correlación de extremo a extremo:** El identificador `X-Correlation-ID` se genera de forma determinista y se incluye en todos los logs de error, aviso o degradación.
4. **Verificación de TLS:** Soporte para `verify_tls` configurable en `Http::withOptions(['verify' => ...])`.

## 3. Estrategia de Pruebas
- `tests/Feature/HmacAgentClientConfigAndSqliteTest.php`:
  - `test_agent_client_resolves_secret_from_config_and_includes_key_id_and_correlation_id`.
  - `test_agent_client_fails_fast_on_non_serializable_payload`.
