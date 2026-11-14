# Análisis - ISSUE-037: Desacoplamiento de LLM, Timeouts Efectivos, Modo Mock Fail-Closed y Trazabilidad de Degradación

## 1. Situación Inicial
- En `jg_plugin_filmmaker/llm.py`:
  1. **Aislamiento Arquitectónico Roto:** Se importa directamente `from app.core.llm import get_chat_model`, lo cual viola la independencia plugin-runtime exigida en `AGENTS.md` (§4). Si `app.core.llm` falla con errores distintos de `ImportError`, la tool colapsa.
  2. **Timeout Ineficaz:** La rama `ainvoke` usa un `ThreadPoolExecutor` dentro de un bloque `with`: cuando `future.result(timeout=30)` expira, el `__exit__` del context manager ejecuta `executor.shutdown(wait=True)` bloqueando el hilo hasta que la llamada externa finalice, neutralizando el timeout real.
  3. **Ausencia de Timeout en Invocación Síncrona:** La rama `llm.invoke()` síncrona no tiene timeout y puede congelar indefinidamente el proceso trabajador.
  4. **Modo Mock Frágil y Gasto No Intencionado:** La detección de mock depende exclusivamente de `PYTEST_CURRENT_TEST`. Fuera de pytest (scripts, otros test runners, local sin `.env`), el plugin opera en modo real ("fail-open" respecto al gasto de tokens y cuotas).
  5. **Falta de Trazabilidad de Degradación:** Cuando el LLM falla y conmuta al fallback determinista, no se añade `degraded=True` ni `source="heuristic_fallback"` en los metadatos de `ToolResult`.
  6. **Riesgo de Prompt Injection en System Prompt:** Los datos de la biblia y candados se interpolaban directamente en el prompt del sistema sin encapsulación ni delimitadores de aislamiento de datos.

## 2. Solución Propuesta

### A. Modo Mock Fail-Closed y conftest.py
- Definir que el plugin opere en modo mock/contingencia segura por defecto:
  - Si `JG_AGENTE_MOCK` es `true` (o 1, yes, on).
  - Si no existe ninguna API key configurada en el entorno (`GROQ_API_KEY`, `OPENAI_API_KEY`, etc.).
  - En pruebas (`PYTEST_CURRENT_TEST` o `CI=true`), forzar mock salvo que explícitamente se especifique `FORCE_REAL_LLM=true`.
- Crear `tests/conftest.py` con fixture `autouse=True` fijando `JG_AGENTE_MOCK=true`.

### B. Desacoplamiento de `app.core.llm` y Gestión de Excepciones
- Envolver la carga dinámica de `app.core.llm` en `try...except Exception:` amplio para que cualquier fallo en la factoría externa no rompa el runtime del plugin y retorne `None` de forma segura.

### C. Timeouts Efectivos Síncronos y Asíncronos
- Implementar un executor de hilos controlado o ejecución acotada con `timeout: float = 30.0` tanto para `.invoke()` como para `.ainvoke()`, evitando que un proveedor colgado bloquee al worker indefinidamente.

### D. Trazabilidad de Degradación en `ToolResult.metadata`
- En `execute_treatment_structuring` y `execute_screenplay_formatter`, registrar:
  - `source: "agent"` y `degraded: False` si se utilizó el modelo cognitivo.
  - `source: "heuristic_fallback"` y `degraded: True` si se utilizó el motor heurístico local.

### E. Blindaje contra Prompt Injection en Contextos Dramáticos
- Envolver `characters_context`, `location_context` y `user_locks` en etiquetas delimitadoras XML: `<bible_context>`, `<character>`, `<location>`, `<pinned_terms>`, con advertencia explícita al LLM de que son datos inertes que no deben interpretarse como directivas operativas.

## 3. Plan de Pruebas
- Escribir `tests/test_llm_resilience_and_mock.py` para validar:
  - Modo mock fail-closed sin API keys.
  - Timeout efectivo en llamadas lentas.
  - Aislamiento arquitectónico ante ausencia o fallos de `app.core.llm`.
  - Inclusión de `degraded: True/False` y `source` en `ToolResult`.
  - Delimitación segura de datos en prompts.
