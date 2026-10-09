# Análisis Técnico — Fase 7: Inferencia Cognitiva Real con LLM y Aislamiento Mock Determinista en Tests

**Fecha:** 2026-10-10  
**Proyecto:** `jg_plugin_filmmaker`, `jg_agente` & `jg_filmmaker` / Ecosistema `javiergalvez-ia`  
**Autor:** Antigravity (Ingeniero Senior de IA y NLP)

---

## 1. Contexto y Objetivos de la Fase 7

Hasta la Fase 6, las herramientas de asistencia dramática y preproducción cinematográfica operaban sobre reglas heurísticas léxicas y expresiones regulares. Esto provocaba fragilidad ante lenguaje natural complejo (por ejemplo, incapacidad de resolver estilo indirecto o inferir personajes dialogantes no registrados en la biblia).

La **Fase 7** activa la inferencia semántica real mediante Modelos de Lenguaje (LLM) satisfaciendo la **Regla de Oro de Aislamiento Determinista**:
1. **Inferencia Semántica Real en Producción:**
   - Conexión de `jg_plugin_filmmaker` con la factoría de modelos de `jg_agente/app/core/llm.py` (Groq, Gemini, Ollama, OpenAI).
   - Inyección de System Prompts especializados para cada etapa dramática:
     * `structuring.py`: Normalización a presente continuo, ambientación espacial y respeto inmutable a `user_locks`.
     * `formatting.py`: Resolución semántica de estilo indirecto a diálogos directos Fountain (`PERSONAJE\n(acotación)\nDiálogo`) e inferencia de nuevos personajes.
     * `breakdown.py`: Evaluación de tensión dramática y cobertura de cámara con delimitadores canónicos `--- shot N (tipo) ---`.
2. **Aislamiento Determinista (Cero Consumo de Tokens en Tests/CI):**
   - En entornos de testing (`pytest`, `php artisan test`, `scripts/check.sh --cicd`), el sistema conmuta automáticamente a mocks deterministas (`MockChatModel` o fallbacks locales) garantizando 100% de reproducibilidad, velocidad y cero consumo de tokens.
3. **Endpoint Canónico en `jg_agente`:**
   - Exposición de `POST /api/v1/tools/{tool_name}` con verificación de firma HMAC-SHA256, inyección del LLM activo y ejecución desacoplada.

---

## 2. Decisiones de Arquitectura e Implementación

### 2.1. Desacoplamiento del Plugin Satélite (`jg_plugin_filmmaker/llm.py`)
Para no imponer dependencias pesadas ni acoplar el plugin con LangChain:
- `invoke_llm_text()` acepta duck-typing: soporta objetos `BaseChatModel` de LangChain (métodos `invoke`/`ainvoke`), funciones callables y diccionarios de mensajes estándar (`[{"role": "system", ...}, {"role": "user", ...}]`).
- `is_mock_mode()` detecta automáticamente la variable `JG_AGENTE_MOCK=true` o la ejecución de `pytest`, degradando inmediatamente a fallback local sin llamadas salientes de red.

### 2.2. Diseño de los System Prompts Cinematográficos
- **`STRUCTURING_SYSTEM_PROMPT`:** Impone tiempo presente de indicativo, inyecta la lista de personajes y la localización, y define los `pinned_terms` como reglas inmutables.
- **`SCREENPLAY_SYSTEM_PROMPT`:** Ordena la resolución del estilo indirecto (ej: *"Javier mira a Juan con miedo y le dice que ya están llegando"* se bifurca en descripción de acción y bloque de diálogo `JAVIER\n(con miedo)\nYa están llegando.`). Infiere personajes como "JUAN" que intervienen dialogando.
- **`BREAKDOWN_SYSTEM_PROMPT`:** Exige el uso exclusivo del formato canónico `--- shot N (tipo) ---` evaluando el ritmo visual de la escena.

### 2.3. Endpoint de Invocación Directa en `jg_agente`
- Módulo [app/api/tools.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/app/api/tools.py) montado bajo `/api/v1/tools/{tool_name}`.
- Valida la autenticación mediante firma HMAC-SHA256 y marca de tiempo (anti-replay ±300s).
- Obtiene la herramienta del `CapabilityRegistry`, le inyecta `get_chat_model(settings)` y retorna el `ToolResult` formateado.

---

## 3. Matriz de Cambios

| Componente | Archivo | Cambio Realizado |
|---|---|---|
| `jg_plugin_filmmaker` | `jg_plugin_filmmaker/llm.py` | Módulo agnóstico de resolución e invocación de LLM con detección de mock. |
| `jg_plugin_filmmaker` | `jg_plugin_filmmaker/structuring.py` | Inferencia con LLM mediante `STRUCTURING_SYSTEM_PROMPT` y preservación de candados. |
| `jg_plugin_filmmaker` | `jg_plugin_filmmaker/formatting.py` | Inferencia con LLM mediante `SCREENPLAY_SYSTEM_PROMPT` y resolución de estilo indirecto. |
| `jg_plugin_filmmaker` | `jg_plugin_filmmaker/breakdown.py` | Inferencia con LLM mediante `BREAKDOWN_SYSTEM_PROMPT` y parseo de planos canónicos. |
| `jg_plugin_filmmaker` | `jg_plugin_filmmaker/tools.py` | Soporte del parámetro `llm_client` en las tres funciones ejecutoras. |
| `jg_plugin_filmmaker` | `tests/test_filmmaker_tools.py` | 3 tests unitarios adicionales validando inferencia con LLM inyectado (13 tests total). |
| `jg_agente` | `app/api/tools.py` | Endpoint `POST /api/v1/tools/{tool_name}` con validación HMAC e inyección de LLM. |
| `jg_agente` | `app/main.py` & `app/api/__init__.py` | Montaje y exposición del enrutador de herramientas directas. |
| `jg_agente` | `tests/test_api_tools.py` | Pruebas de autorización HMAC y ejecución de herramientas. |
| `jg_filmmaker` | `app/Services/JgAgente/AgentClientService.php` | Envío de cabecera canónica `X-Agent-ID: jg_filmmaker`. |
