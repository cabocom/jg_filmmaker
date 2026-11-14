# Walkthrough - ISSUE-037: Desacoplamiento de LLM, Timeouts Efectivos, Modo Mock Fail-Closed y Trazabilidad de Degradación

## Resumen de la Solución
Se resolvió la fragilidad arquitectónica y el riesgo de bloqueos o consumo accidental de cuotas en `jg_plugin_filmmaker/llm.py`:
- **Modo Mock Fail-Closed:** Se implementó una evaluación estricta en `is_mock_mode()`: si no existen API keys en el entorno, opera automáticamente en modo mock/contingencia determinista sin consumir tokens. Se añadió `tests/conftest.py` con una fixture `autouse=True` que garantiza `JG_AGENTE_MOCK=true` en todos los tests.
- **Desacoplamiento Arquitectónico (AGENTS.md §4):** Se eliminó la importación estática de `app.core.llm`. La resolución dinámica captura cualquier excepción externa (`Exception`) evitando que errores del core de `jg_agente` bloqueen o colapsen el plugin satélite.
- **Timeouts Efectivos Síncronos y Asíncronos:** Se reemplazó el `with ThreadPoolExecutor()` bloqueante por un executor global reutilizado (`filmmaker_llm`) con `future.result(timeout=timeout)` y `asyncio.wait_for(...)`, previniendo que llamadas lentas o caídas de proveedores bloqueen los hilos de trabajo.
- **Trazabilidad de Degradación Cognitiva:** `execute_treatment_structuring` y `execute_screenplay_formatter` ahora propagan en `metadata` los atributos canónicos `source: "agent" | "heuristic_fallback"` y `degraded: False | True`.
- **Blindaje Anti Prompt-Injection:** Se encapsularon los datos contextuales de la biblia dramática en etiquetas XML (`<bible_characters>`, `<bible_location>`, `<pinned_terms>`) con directivas explícitas de tratar el contenido como datos inertes no ejecutables.

## Cambios Realizados

### 1. jg_plugin_filmmaker/llm.py
- Executor global `_LLM_EXECUTOR = concurrent.futures.ThreadPoolExecutor(max_workers=4)`.
- `is_mock_mode`: Verificación de `JG_AGENTE_MOCK`, tests/CI y presencia de API keys (fail-closed).
- `get_active_llm`: Desacoplamiento dinámico sin dependencias directas.
- `invoke_llm_text`: Parámetro `timeout=30.0` y timeout real para interfaces síncronas (`.invoke()`), asíncronas (`.ainvoke()`) y funciones callables.

### 2. jg_plugin_filmmaker/structuring.py y formatting.py
- Encapsulación de variables de usuario en tags XML delimitadas y advertencia anti prompt-injection en `STRUCTURING_SYSTEM_PROMPT` y `SCREENPLAY_SYSTEM_PROMPT`.
- Soporte para `return_meta: bool = True` que retorna `(output, {"source": ..., "degraded": ...})`.

### 3. jg_plugin_filmmaker/tools.py
- Propagación de metadatos cognitivos `source` y `degraded` a `ToolResult.metadata` tanto en estructuración como en formateo Fountain.

### 4. Tests y Fixtures
- `tests/conftest.py`: Fixture autouse garantizando `JG_AGENTE_MOCK=true`.
- `tests/test_llm_resilience_and_mock.py`: 7 pruebas automatizadas cubriendo fail-closed, desacoplamiento, timeout estricto (< 0.5s ante LLM colgado), reporte de `degraded`/`source` y encapsulación XML anti prompt-injection.

## Verificación de Calidad
- **Python**: `bash scripts/check.sh` -> Ruff (0 errores) y 47 tests pasando en 0.23s.
- **PHP**: `./vendor/bin/pint --test` (Passed) y `php artisan test` (144 tests pasando, 689 aserciones, 0 errores).
