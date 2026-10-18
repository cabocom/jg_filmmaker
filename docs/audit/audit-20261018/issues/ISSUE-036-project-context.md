# Project Context Update - ISSUE-036: Pydantic v2 Strict Typing y Submodelos de Dominio

## Módulos Afectados
- `jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py`
- `jg_plugin_filmmaker/jg_plugin_filmmaker/structuring.py`
- `jg_plugin_filmmaker/tests/test_pydantic_strict_typing.py`

## Decisiones Técnicas y Arquitectura
1. **Modelos de Dominio vs Tolerancia de Esquema Eloquent:**
   - Para prevenir incompatibilidades con Laravel Eloquent (`User::toArray()` o `Scene::toArray()` que exportan `id`, `uuid`, `created_at`, `project_id`), se configuró `model_config = ConfigDict(extra="ignore")` en lugar de `forbid`. De este modo, los submodelos filtran y validan estrictamente los atributos que el agente consume sin romper la serialización de clientes heterogéneos.
2. **Protocolo para Clientes LLM (`LLMClientProtocol`):**
   - Se introdujo un `Protocol` con decorador `@runtime_checkable` que formaliza el contrato del cliente cognitivo (`generate(prompt, **kwargs)`), reemplazando las anotaciones genéricas `Any`.
3. **Ofuscación de Mensajes de Error Internos:**
   - La captura genérica de excepciones ya no concatena `str(e)` en la respuesta JSON pública del agente (lo cual podría exponer detalles de infraestructura o rutas del filesystem). En su lugar, se registra la traza completa internamente con `logger.exception(...)` y se retorna un mensaje estructurado con un código de error de metadatos (`error_code`).
