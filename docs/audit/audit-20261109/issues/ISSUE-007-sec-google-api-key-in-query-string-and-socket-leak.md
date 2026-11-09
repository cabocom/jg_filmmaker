# [ISSUE-007] Exposición de `GOOGLE_AI_API_KEY` en Query String, Falta de Validación de `base_url` y Fuga de Sockets en `storyboard.py`

- **Severidad:** Media
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/TextToSpeechService.php:284`, `jg_plugin_filmmaker/jg_plugin_filmmaker/storyboard.py:217-226`
- **Categoría:** Seguridad

## 1. Descripción
Se han identificado tres problemas en la integración HTTP con Google AI Studio (`generativelanguage.googleapis.com`):
1. **API Key en Parámetro URL (`?key=...`):** Tanto `TextToSpeechService::synthesizeWithGoogleAi()` en Laravel como `generate_storyboard()` en `jg_plugin_filmmaker` concatenan la clave secreta `GOOGLE_AI_API_KEY` directamente en la URL de la petición (`?key={$this->googleApiKey}`). Cuando ocurre un timeout, error de DNS o excepción de transporte en `Http::post` (Guzzle) o `httpx`, la URL completa (incluyendo `?key=AIza...`) puede volcarse en mensajes de excepción o logs de errores si no se sanea previamente.
2. **Parámetro `base_url` No Validado en `generate_storyboard`:** `StoryboardGeneratorArgs` acepta `base_url` como parámetro de entrada del tool payload y `storyboard.py` realiza un `http_client.post(f"{resolved_url}/models/...")` anexando la `GOOGLE_AI_API_KEY` del entorno del servidor si `api_key` no fue provista en el payload. Si un llamador envía un `base_url` apuntando a un host externo bajo su control, el plugin enviaría la `GOOGLE_AI_API_KEY` del servidor hacia ese host (SSRF + exfiltración de credencial).
3. **Fuga de Conexiones TCP (`httpx.Client` sin Context Manager):** En `storyboard.py:217`, cuando `http_client` es `None`, se instancia `http_client = httpx.Client(timeout=60.0)` sin cerrarlo en un bloque `finally` ni usar `with httpx.Client(...)`, dejando el pool de sockets abierto tras cada generación de viñeta.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/TextToSpeechService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/TextToSpeechService.php#L283-L286):
```php
        foreach ($candidateModels as $currentModel) {
            $endpoint = "{$this->googleBaseUrl}/models/{$currentModel}:generateContent?key={$this->googleApiKey}";
```

Y en [`jg_plugin_filmmaker/jg_plugin_filmmaker/storyboard.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/storyboard.py#L217-L226):
```python
    if http_client is None:
        http_client = httpx.Client(timeout=60.0)

    endpoint = (
        f"{resolved_url}/models/{resolved_model}:generateContent?key={resolved_key}"
    )

    try:
        resp = http_client.post(
            endpoint,
            json=payload,
            headers={"Content-Type": "application/json"},
        )
```

## 3. Impacto Operativo o Riesgo
- **Exposición Accidental de Credenciales en Logs/Trazas:** Las URLs se registran por defecto en excepciones de Guzzle/httpx y telemetría de red, mientras que las cabeceras HTTP se redactan o excluyen.
- **Agotamiento de Descriptores de Archivo (File Descriptors):** La creación repetida de `httpx.Client` sin `.close()` en un worker Python de larga duración (`jg_agente`) degrada progresivamente los sockets disponibles del proceso.

## 4. Solución o Mejora Recomendada
1. Enviar siempre la credencial mediante la cabecera estándar `x-goog-api-key` en lugar del query string `?key=...`.
2. Validar que `resolved_url` pertenezca a una lista blanca (`https://generativelanguage.googleapis.com/...`) antes de adjuntar la clave de entorno.
3. Cerrar determinísticamente el cliente `httpx` creado internamente:

```python
    ALLOWED_HOSTS = {"generativelanguage.googleapis.com"}
    parsed = urllib.parse.urlparse(resolved_url)
    if parsed.scheme != "https" or parsed.hostname not in ALLOWED_HOSTS:
        raise ValueError(f"Untrusted Google AI base_url host: {parsed.hostname}")

    owns_client = http_client is None
    if owns_client:
        http_client = httpx.Client(timeout=60.0)

    endpoint = f"{resolved_url}/models/{resolved_model}:generateContent"
    try:
        resp = http_client.post(
            endpoint,
            json=payload,
            headers={
                "Content-Type": "application/json",
                "x-goog-api-key": resolved_key,
            },
        )
        # ...
    finally:
        if owns_client:
            http_client.close()
```

