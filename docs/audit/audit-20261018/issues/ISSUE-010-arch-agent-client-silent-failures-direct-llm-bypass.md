# [ISSUE-010] `AgentClientService::callTool` traga todos los errores y salta a un LLM directo con clave global, saltándose `jg_agente`, telemetría y cuota

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/JgAgente/AgentClientService.php:78-118,945-1126`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
- Fallos HTTP (`ConnectionException`, `Exception`, respuesta no 2xx) se **descartan sin log** (bloques `catch` vacíos; si hay respuesta no exitosa ni siquiera se registra el status). Un 401 por firma HMAC incorrecta, un 429 o un 500 son indistinguibles de "agente caído".
- Sin reintentos con backoff ni circuit breaker: con `jg_agente` caído, cada acción paga el timeout completo (30 s) dentro de la petición Livewire y vuelve a intentarlo en la siguiente.
- Fuera de `testing`, tras el fallo se llama a `callDirectLlm()` **directamente al proveedor (Groq/OpenAI/Gemini)** con una API key global leída de la configuración. Esto: (a) viola el desacoplamiento (`jg_filmmaker` ↔ `jg_agente`, AGENTS.md §4) y la política de que toda inferencia pasa por el agente; (b) envía guiones inéditos a un tercero sin consentimiento ni trazabilidad en `jg_agente`; (c) hace que el consumo no pase por el control de cuota/telemetría del agente (en `jg_filmmaker` solo se estima desde caracteres); (d) mezcla temperatura fija 0.7 sin determinismo.
- El último recurso es un fallback determinista que devuelve `success: true` como si fuese IA: el usuario cree que el agente ha estructurado su texto; no hay flag `degraded`/`source` en la respuesta ni en la UI.
- `Log::warning("Direct LLM call failed ({$status}): {$response->body()}")` vuelca el cuerpo de la respuesta del proveedor (puede incluir parte del prompt/PII).
- `callTool` tampoco valida que `$data['success'] === true`: acepta cualquier JSON array (incluido `{"error":...}` con 200).

## 2. Evidencia de Código
```php
} catch (ConnectionException $e) {
    // Sin conexión a microservicio jg_agente
} catch (Exception $e) {
    // Error en petición
}
if (! app()->environment('testing')) {
    $directResult = $this->callDirectLlm($toolName, $payload);   // Groq/OpenAI/Gemini con clave global
```

## 3. Impacto Operativo o de Seguridad
Pérdida de observabilidad, fuga de contenido a terceros, eludir costes/cuotas y política de seguridad del ecosistema, resultados degradados presentados como éxito (pérdida de confianza y de trabajo creativo si luego se bloquean con 🔒).

## 4. Solución Recomendada
- Registrar cada fallo con `Log::warning` (status, `X-Correlation-ID`, tool; **sin** payload ni secretos).
- `Http::retry([200, 800], throw: false)` solo para errores transitorios (5xx/timeout), no para 4xx; circuit breaker con `Cache` (abrir 60 s tras N fallos).
- Eliminar `callDirectLlm` (o dejarlo detrás de `config('services.llm.direct_enabled')` desactivado por defecto y nunca en producción).
- Devolver un DTO tipado: `['success'=>bool,'source'=>'agent|fallback','degraded'=>bool,'output'=>...]` y mostrar aviso en la UI cuando `degraded`.
- Exigir `($data['success'] ?? false) === true`; en caso contrario tratar como fallo.
- Tests con `Http::preventStrayRequests()` + `Http::fake` por escenario (401, 429, 500, timeout, JSON inválido).
