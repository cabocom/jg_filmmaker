# [ISSUE-031] Diseño de la autenticación HMAC inter-servicio: secreto compartido único, sin firma de respuesta, query/cabeceras fuera de la firma y sin verificación de contrato

- **Severidad:** Media
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `app/Services/JgAgente/AgentClientService.php:30-70,85-100`, `docs/05_hmac_agent_integration.md`, `jg_plugin_filmmaker/docker-compose.yml:57-60,109`
- **Categoría:** Seguridad

## 1. Descripción del Problema
El cálculo canónico en `generateSignatureHeaders()` es correcto (METHOD, PATH, TIMESTAMP, SHA256(body), NONCE con HMAC-SHA256, UUID por petición) y el cliente es sólo emisor; la **verificación (ventana ±300 s, nonce de un solo uso, comparación en tiempo constante) reside en `jg_agente`, fuera de este alcance**, y no puede comprobarse desde aquí. Hallazgos verificables:

1. **Un único secreto compartido** (`JG_AGENTE_SECRET`) para todos los servicios: el compose del plugin lo reutiliza como `JG_CONVERSATIONAL_SECRET`, `JG_DEVOPS_SECRET` y `PERMANENT_AGENT_SECRET`. Compromiso de cualquier cliente (p. ej. por ISSUE-001) = suplantación de todos; sin rotación ni identificador de clave (`kid`), y `X-Agent-ID` no forma parte del material firmado, por lo que el agente no puede asociar secreto↔emisor.
2. **No se firma la query string ni `Content-Type`**: la canónica sólo incluye `$path`; si en el futuro se añaden parámetros, quedan sin protección.
3. **`X-Correlation-ID` es un UUID distinto del nonce** y no se firma; no hay correlación entre log local y del agente salvo que el agente la registre.
4. **Sin autenticación de la respuesta**: el cliente confía en cualquier JSON recibido (incluida salida de otra URL mal configurada, `http://` sin TLS dentro de `jg_network`). No hay `Http::` con `verify` forzado ni pinning y `JG_AGENTE_URL` admite `http://`.
5. El cuerpo se serializa con `json_encode(...) ?: '{}'`: si el payload no es codificable (UTF-8 inválido) se firma y envía `{}` (petición vacía válida) en vez de abortar.
6. El timestamp usa `time()` local sin comprobación de deriva; sin NTP el agente rechazará (401) y el cliente lo ocultará (ISSUE-010).

## 2. Evidencia de Código
```php
$canonicalString = strtoupper($method)."\n".$path."\n".$timestamp."\n".$bodySha256."\n".$nonce;
$signature = hash_hmac('sha256', $canonicalString, $this->secret);
...
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
```

## 3. Impacto Operativo o de Seguridad
Radio de impacto ampliado ante el compromiso de un solo servicio; riesgo de peticiones vacías firmadas; menor trazabilidad forense.

## 4. Solución Recomendada
- Secreto por servicio (`kid` + secreto) y rotación con ventana de solape; incluir `X-Agent-ID`/`kid` en la canónica.
- Incluir `query` y `Content-Type` en la canónica; usar `JSON_THROW_ON_ERROR`.
- Firmar la respuesta (`X-Response-Signature`) o usar mTLS en `jg_network`.
- Usar el mismo identificador para nonce y correlación (o incluir el correlation-id en la firma) y registrarlo en cada log.
- Test de contrato compartido (vectores de firma fijos) entre `jg_filmmaker` y `jg_agente`, incluyendo caso de timestamp caducado y nonce repetido (en el repositorio del agente).
