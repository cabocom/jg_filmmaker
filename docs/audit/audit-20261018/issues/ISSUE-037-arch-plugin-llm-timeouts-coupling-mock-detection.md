# [ISSUE-037] `llm.py`: timeout ineficaz, ausencia de timeout en `.invoke()`, acoplamiento a `app.core.llm` y modo mock inferido de variables implícitas

- **Severidad:** Media
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/llm.py:17-24,39-48,52-118`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
- **Timeout ineficaz:** en la rama `ainvoke` dentro de un loop activo se usa `with ThreadPoolExecutor() as executor: future.result(timeout=30)`. Al expirar el timeout se lanza `TimeoutError`, pero el bloque `with` ejecuta `executor.shutdown(wait=True)` y **espera a que termine la corrutina** de todos modos: el timeout no limita el tiempo real. Además crea un executor y un event loop nuevo por llamada, y un cliente `ainvoke` ligado al loop principal (httpx/AsyncClient) se usa desde otro loop (riesgo de `RuntimeError: Event loop is closed`).
- La rama `.invoke()` (síncrona, la más usada con LangChain) **no tiene ningún timeout ni límite de reintentos**; bloquea el hilo del worker hasta que el proveedor responda.
- `get_active_llm()` hace `from app.core.llm import get_chat_model`: depende de un **módulo interno de `jg_agente`** (`app.core`), violando el aislamiento de plugin vs runtime (AGENTS.md §4); sólo captura `ImportError`, de modo que cualquier otra excepción de la factoría rompe la tool.
- **Mock implícito:** `is_mock_mode()` considera "test" la presencia de `PYTEST_CURRENT_TEST` y exige `FORCE_REAL_LLM` para lo contrario. Funciona, pero depende de una variable que sólo pytest define; en un runner distinto (unittest, scripts) o con `JG_AGENTE_MOCK` ausente, el plugin invoca el LLM real y consume tokens. Fuera de pytest, el valor por defecto es "real" (fail-open respecto al gasto).
- Los errores se registran con `logger.warning("... %s", e)` y se conmuta silenciosamente al fallback heurístico, devolviendo `success=True` sin indicar `degraded` en `metadata` (mismo problema que ISSUE-010).
- Los prompts embeben contenido del usuario (`pinned_terms`, `bio`) en el **system prompt** sin delimitar: posibilidad de prompt injection desde datos de la Biblia.

## 2. Evidencia de Código
```python
with concurrent.futures.ThreadPoolExecutor() as executor:
    future = executor.submit(asyncio.run, llm.ainvoke(messages))
    response = future.result(timeout=30)          # el `with` espera igualmente
...
from app.core.llm import get_chat_model            # acoplamiento a jg_agente
return "PYTEST_CURRENT_TEST" in os.environ and os.getenv("FORCE_REAL_LLM") != "true"
```

## 3. Impacto Operativo o de Seguridad
Workers bloqueados indefinidamente ante lentitud del proveedor, gasto de tokens accidental fuera de pytest, y dependencia frágil de internals del agente.

## 4. Solución Recomendada
- Inyectar el cliente vía el contrato del SDK (`jg_sdk`) y definir `LlmClient(Protocol)` con `ainvoke(messages, timeout)`; las tools deberían ser `async` y usar `await asyncio.wait_for(llm.ainvoke(...), timeout=30)`.
- Configurar `timeout`/`max_retries` en el cliente LangChain (`ChatGroq(timeout=30, max_retries=1)`).
- Modo mock **explícito y fail-closed**: `JG_AGENTE_MOCK` por defecto `true` salvo que `JG_AGENTE_ENV=production` y haya clave; añadir fixture `conftest.py` autouse que fije `JG_AGENTE_MOCK=true`.
- Marcar `metadata["degraded"]=True` y `source` cuando se use el fallback.
- Delimitar datos de usuario en el prompt (`<bible>...</bible>`) e indicar al modelo que son datos, no instrucciones.
