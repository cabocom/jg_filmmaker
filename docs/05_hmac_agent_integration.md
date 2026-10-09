# 05. Integración Inter-Servicio HMAC-SHA256 (`jg_filmmaker`)

[← 04. Biblia Contextual](04_context_bible.md) | [06. Gestión de Medios y FFmpeg →](06_media_takes_ffmpeg.md)

---

## 1. Topología de Comunicación y Desacoplamiento

Dentro del ecosistema `javiergalvez-ia`, `jg_filmmaker` y `jg_agente` operan como dos aplicaciones independientes. No comparten bases de datos ni sockets internos.

Cualquier invocación de herramientas satélite del plugin `jg_plugin_filmmaker` se realiza mediante peticiones HTTP REST punto a punto, autenticadas criptográficamente con HMAC-SHA256 para evitar accesos no autorizados y suplantación de peticiones.

---

## 2. Protocolo de Firma Criptográfica HMAC-SHA256

El cliente [AgentClientService](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php) implementa el algoritmo canónico del ecosistema:

### 2.1. String Canónico
```text
canonical_string = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + SHA256(RAW_BODY)
```

### 2.2. Cálculo de la Firma
```php
$timestamp = (string) time();
$rawBody = json_encode($payload);
$bodySha256 = hash('sha256', $rawBody);
$canonicalString = "POST\n{$endpoint}\n{$timestamp}\n{$bodySha256}";
$signature = hash_hmac('sha256', $canonicalString, $this->secret);
```

### 2.3. Cabeceras HTTP Obligatorias
- `X-Signature`: Firma hexadecimal generada con la clave compartida (`JG_AGENTE_SECRET`).
- `X-Timestamp`: Marca de tiempo Unix en segundos (ventana de aceptación: ±300s para mitigar ataques de repetición).
- `X-Correlation-ID`: Identificador UUIDv4 para trazabilidad distribuida en los logs del agente.

---

## 3. Endpoints de Herramientas Invocadas

| Herramienta Satélite | Endpoint en jg_agente | Payload Clave | Propósito |
|---|---|---|---|
| `treatment_structuring` | `/api/v1/tools/treatment_structuring` | `raw_text`, `characters_context`, `location_context`, `user_locks` | Convierte texto informal a prosa dramática estructurada. |
| `screenplay_formatter` | `/api/v1/tools/screenplay_formatter` | `structured_text`, `characters_context`, `location_context`, `user_locks` | Transforma a formato Fountain/Courier con diálogos. |
| `shot_breakdown_detector` | `/api/v1/tools/shot_breakdown_detector` | `screenplay_text` | Desglosa planos técnicos de rodaje (`--- shot N (tipo) ---`). |

---

## 4. Fallback Determinista Local para Desarrollo y Testing

Para garantizar alta resiliencia y permitir el desarrollo o ejecución de tests unitarios sin requerir tener el runtime `jg_agente` levantado:
- Si la petición HTTP falla por timeout o error de conexión, o si el entorno está en modo testing/offline, `AgentClientService::executeDeterministicFallback()` ejecuta una transformación heurística local.
- Este mecanismo asegura que el flujo de trabajo del cineasta nunca se interrumpa abruptamente.
