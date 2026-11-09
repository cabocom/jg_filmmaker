# [ISSUE-025] Cadena Canónica HMAC-SHA256 No Vincula `X-Key-Id`, `X-Agent-ID` ni Query String dentro de la Firma (Pendiente de `audit-20261018 / ISSUE-031`)

- **Severidad:** Baja
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/JgAgente/AgentClientService.php:48-74`
- **Categoría:** Seguridad

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-031`) se recomendó incorporar un identificador de clave (`X-Key-Id`), asociar criptográficamente el emisor (`X-Agent-ID` / `X-Key-Id`) dentro de la cadena canónica firmada con HMAC-SHA256, y utilizar el mismo identificador o vincular `X-Correlation-ID` a la firma.

En el código actual (`AgentClientService::generateSignatureHeaders()`), aunque se añadieron las cabeceras HTTP `X-Key-Id`, `X-Agent-ID` y `X-Correlation-ID`, **ninguna de las tres forma parte de `$canonicalString`**, que continúa calculándose únicamente sobre `METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + SHA256(BODY) + "\n" + NONCE`. Inoltre, `$correlationId` y `$nonce` se generan como dos UUIDv4 distintos en lugar de estar vinculados en la firma.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/JgAgente/AgentClientService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php#L48-L74):
```php
    public function generateSignatureHeaders(string $method, string $path, string $rawBody, ?int $timestamp = null, ?string $nonce = null): array
    {
        $timestamp = $timestamp ?? time();
        $nonce = $nonce ?? (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        $bodySha256 = hash('sha256', $rawBody);

        $canonicalString = strtoupper($method)."\n"
            .$path."\n"
            .$timestamp."\n"
            .$bodySha256."\n"
            .$nonce;

        $signature = hash_hmac('sha256', $canonicalString, $this->secret);

        return [
            'X-Agent-ID' => 'jg_filmmaker',
            'X-Key-Id' => $this->keyId,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'X-Correlation-ID' => $correlationId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
```

## 3. Impacto Operativo o Riesgo
- **Cabeceras de Identidad y Correlación No Autenticadas:** Un intermediario o proxy en la red interna podría alterar `X-Agent-ID`, `X-Key-Id` o `X-Correlation-ID` sin invalidar `X-Signature`.

## 4. Solución o Mejora Recomendada
1. Unificar `$correlationId = $nonce` (o incluir `$correlationId`, `$this->keyId` y `X-Agent-ID` en una cabecera firmada / versión v2 de firma coordinada con el validador de `jg_agente`).
2. Asegurar que `$path` incluya siempre la query string canónica si se pasan parámetros URL.

