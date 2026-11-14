# Análisis Técnico: [ISSUE-010] `AgentClientService::callTool` traga todos los errores y salta a un LLM directo con clave global, saltándose `jg_agente`, telemetría y cuota

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Categoría:** Arquitectura / Resiliencia / Gobernanza de Secretos
- **Vulnerabilidades / Errores:** CWE-390 (Detection of Error Condition Without Action), CWE-1068 (Inconsistency Between Implementation and Documented Design), CWE-532 (Insertion of Sensitive Information into Log File)

---

## 1. Diagnóstico del Estado Previo

1. **Silenciamiento Ciego de Errores HTTP:**
   - En `AgentClientService::callTool()`:
     ```php
     try { ... }
     catch (ConnectionException $e) { /* vacío */ }
     catch (Exception $e) { /* vacío */ }
     ```
   - Si `jg_agente` devolvía 401 (HMAC inválido), 422 (payload mal formado) o 500 (crash interno), no quedaba ningún registro en `storage/logs/laravel.log`. La falta de observabilidad impedía diagnosticar problemas reales de conectividad o autenticación entre microservicios.

2. **Bypass no Autorizado al Proveedor LLM (`callDirectLlm`):**
   - Ante cualquier fallo de `jg_agente`, el código saltaba automáticamente a `callDirectLlm()`, invocando de forma directa a Groq, OpenAI o Gemini con API keys globales.
   - Consecuencias:
     - Violación del principio de arquitectura desacoplada (AGENTS.md §4): `jg_filmmaker` eludía la pasarela `jg_agente`.
     - Exfiltración no auditada de guiones inéditos de usuarios hacia APIs externas comerciales.
     - Consumo sin computar en el agente central ni en su control de cuota.

3. **Fuga de Información en Logs:**
   - La línea `Log::warning("Direct LLM call failed ({$response->status()}): {$response->body()}")` volcaba el cuerpo íntegro del proveedor externo, el cual en ocasiones incluye fragmentos del prompt del usuario o mensajes de error con PII.

4. **Aceptación Indiscriminada de Respuestas Erróneas:**
   - `callTool()` solo comprobaba `$response->successful()` (status HTTP 2xx) y retornaba `$response->json()` sin validar si el cuerpo contenía `['success' => true]`.

5. **Falta de Trazabilidad sobre Degradación:**
   - Ni la respuesta ni la telemetría informaban si el resultado era fruto del agente, de un LLM directo o de una heurística determinista local (`degraded`).

---

## 2. Alternativas Evaluadas

1. **Permitir `callDirectLlm` en cualquier entorno si falla `jg_agente`:**
   - *Descartada:* Transgrede los principios arquitectónicos del ecosistema y la gobernanza de privacidad y telemetría de `javiergalvez-ia`. Toda inferencia de producción debe transitar por `jg_agente`.
2. **Reintentos inteligentes, logging estructurado sin PII, control estricto de direct LLM y trazabilidad de degradación (Opción Seleccionada):**
   - *Ventajas:*
     - Incorporación de `Http::retry(2, 200, throw: false)` para tolerar fluctuaciones transitorias de red (5xx / timeouts).
     - Registro estructurado con `Log::warning` de códigos de respuesta, latencia y excepciones sin volcar payloads ni cuerpos de respuesta sensibles.
     - `callDirectLlm` queda deshabilitado en producción y gobernado por el flag explícito `config('services.llm.direct_enabled', false)`.
     - Validación estricta del contrato `ToolResult` (`$data['success'] === true`).
     - Inclusión en la telemetría y resultados de los atributos `source: 'agent'|'direct_llm'|'deterministic_fallback'` y `degraded: bool`.

---

## 3. Plan de Implementación

1. **Refactorización de `AgentClientService` (`app/Services/JgAgente/AgentClientService.php`):**
   - En `callTool()`:
     - Configurar `Http::retry(2, 200, throw: false)`.
     - Si la respuesta HTTP no es exitosa o `($data['success'] ?? false) !== true`, registrar advertencia estructurada con `Log::warning`.
     - Capturar y loguear `ConnectionException` y `Exception` con detalles de error.
     - Condicionar `callDirectLlm()` a `! app()->isProduction() && config('services.llm.direct_enabled', false)`.
     - Saneamiento de logs en `callDirectLlm()`, suprimiendo `$response->body()`.
     - Enriquecer la telemetría y el retorno con `source` y `degraded`.

2. **Pruebas Automatizadas (`tests/Feature/AgentClientRobustnessTest.php`):**
   - Validar que ante 500 o fallos de red se registra el log estructurado.
   - Validar que respuestas con `success: false` se reconocen como fallidas.
   - Validar que en producción `callDirectLlm` no se invoca.
   - Validar que los resultados de fallback marcan `degraded = true`.
