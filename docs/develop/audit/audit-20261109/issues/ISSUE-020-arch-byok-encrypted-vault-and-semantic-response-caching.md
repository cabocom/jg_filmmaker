# [ISSUE-020] Bóveda Cifrada BYOK (Bring Your Own Key) por Usuario/Proyecto y Caché Semántica de Transformaciones IA

- **Severidad:** Idea de Mejora
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/JgAgente/AgentClientService.php:76-152`, `jg_plugin_filmmaker/jg_plugin_filmmaker/llm.py:39-145`
- **Categoría:** Arquitectura

## 1. Descripción
Actualmente, todas las peticiones de inferencia textual (`groq` / `openai` / `gemini`) y multimodal (`gemini-2.5-flash-image` y `gemini-3.8-flash-tts`) consumen una única clave global del servidor (`GOOGLE_AI_API_KEY` / `GROQ_API_KEY`), controlada mediante un límite diario de tokens por usuario (`users.daily_token_limit`).

A medida que crecen los usuarios y la longitud de los guiones, surgen dos necesidades estratégicas:
1. **Soporte BYOK (Bring Your Own Key) Seguro:** Permitir que usuarios avanzados o productoras conecten sus propias claves de API (Google AI Studio, OpenAI, Groq) para operar sin consumir el cupo compartido del servidor ni verse afectados por los límites de RPM del Tier 1 global. Curiosamente, `jg_plugin_filmmaker` ya soporta recibir `api_key`, `provider` y `model` dinámicos por petición en los esquemas de `tools.py` y `llm.py`, pero `jg_filmmaker` aún no dispone de una bóveda cifrada por usuario para almacenarlos y gestionarlos con seguridad.
2. **Caché Determinista por Hash de Contenido en Transformaciones del Pipeline:** Cuando un usuario ejecuta un pipeline de escena o proyecto varias veces sin haber modificado el texto de ciertos bloques ni la Biblia, `ScriptPipelineService` vuelve a invocar al LLM con exactamente el mismo payload (`raw_text` + `characters_context` + `location_context` + `user_locks`), pagando dos veces por la misma transformación.

## 2. Evidencia de Código
En [`jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py#L220-L234), el plugin ya está preparado para recibir credenciales por petición:
```python
    api_key: str | None = Field(
        default=None, description="API Key opcional para proveedor LLM (BYOK)"
    )
    provider: str | None = Field(
        default=None, description="Proveedor LLM (groq, openai, gemini)"
    )
    model: str | None = Field(default=None, description="Modelo LLM específico")
```

## 3. Impacto Operativo o Riesgo
- **Cuello de Botella en Cuota Global de API:** Un pico de uso simultáneo por parte de varios usuarios en una clase o rodaje agota el límite de peticiones por minuto (RPM) de la cuenta global de Google AI Studio / Groq para todos los demás usuarios.

## 4. Solución o Mejora Recomendada
1. **Bóveda BYOK con Cifrado Autenticado (`AES-256-GCM` vía Laravel Crypt):**
   - Añadir una entidad `UserApiCredential` con columna `encrypted_api_key` (cast `'encrypted'`), `key_fingerprint` (últimos 4 caracteres enmascarados para UI) y `provider` (`google`, `groq`, `openai`).
   - En `AgentClientService` y `TextToSpeechService`, si el usuario propietario del proyecto tiene una credencial BYOK activa y verificada para el proveedor correspondiente, desencriptarla exclusivamente en memoria en el instante de construir la petición hacia `jg_agente` / Google AI, eximiendo esa llamada del descuento sobre el `daily_token_limit` global de la plataforma y marcando `is_byok = true` en `agent_request_logs`.
   - Prohibir estrictamente cualquier volcado de `api_key` en logs o trazas de auditoría mediante `#[SensitiveParameter]` en PHP 8.4 y `SecretStr` en Pydantic v2.
2. **Caché de Transformación por Hash Canónico (`sha256`):**
   - Antes de invocar `treatment_structuring`, `screenplay_formatter` o `shot_breakdown_detector`, calcular `hash('sha256', json_encode([$toolName, $payloadWithoutCredentials]))`. Si existe un resultado válido reciente en caché para ese hash exacto, devolverlo en `0 ms` con coste `0 tokens`.

## 5. Resolución Técnica y Validación

Se ha implementado íntegramente la arquitectura de boveda cifrada BYOK y caché semántica determinista:

1. **Bóveda Cifrada BYOK (`UserApiCredential` & `ProjectEncryptionVaultService`):**
   - Migración `2026_11_14_230000_create_user_api_credentials_table` creando la tabla `user_api_credentials` con columna `encrypted_api_key` (cifrado AES-256 autenticado vía Laravel Crypt), `key_fingerprint` (`...XXXX` enmascarado para UI) y relación con `User`.
   - Servicio `App\Services\Security\ProjectEncryptionVaultService` para almacenamiento seguro con `#[SensitiveParameter]`, resolución en memoria y revocación de credenciales.
   - En `TextToSpeechService`, resolución transparente de credenciales Google AI BYOK del usuario propietario en memoria, eximiéndolo del cómputo de tokens diarios y registrando `is_byok = true` en telemetría.
   - En `ScriptPipelineService::assertUserQuotaAvailable`, exención automática de cuotas de plataforma para usuarios con credenciales BYOK activas.
   - En `User::getTokensUsedToday()`, exclusión de registros `is_byok = true` de la cuota diaria consumida de la plataforma.

2. **Caché Semántica Determinista por Hash Canónico (`SemanticPipelineCacheService`):**
   - Servicio `App\Services\JgAgente\SemanticPipelineCacheService` con normalización canónica recursiva (omitiendo credenciales efímeras, nonces o timestamps) y cálculo de hash `sha256`.
   - Almacenamiento y recuperación en 0 ms con 0 tokens imputados en `AgentHttpTransport` para herramientas de transformación (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`).

3. **Protección de Secretos en Plugin Satélite (`jg_plugin_filmmaker`):**
   - Esquemas `TreatmentStructuringArgs` y `ScreenplayFormatterArgs` actualizados con `SecretStr` para campos `api_key` opcionales BYOK.

4. **Validación:**
   - Suite completa de pruebas en `tests/Feature/ProjectEncryptionVaultTest.php` (5 tests de integración).
   - PHPUnit: **316 tests pasando al 100%** (1.470 assertions). Pint: **0 infracciones**.
   - Pytest en `jg_plugin_filmmaker`: **73 tests pasando al 100%**, cobertura **86.26%** y Ruff 0 errores.


