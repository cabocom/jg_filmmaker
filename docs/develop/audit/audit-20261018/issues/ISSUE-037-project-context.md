# Project Context Update - ISSUE-037: Desacoplamiento de LLM, Timeouts y Mock Fail-Closed

## Módulos Afectados
- `jg_plugin_filmmaker/llm.py`
- `jg_plugin_filmmaker/structuring.py`
- `jg_plugin_filmmaker/formatting.py`
- `jg_plugin_filmmaker/tools.py`
- `jg_plugin_filmmaker/tests/conftest.py`
- `jg_plugin_filmmaker/tests/test_llm_resilience_and_mock.py`

## Decisiones Técnicas y Arquitectura
1. **Principio Fail-Closed para Inferencia Cognitiva:**
   - Para prevenir gastos accidentales de tokens o fallos de conexión cuando no se han inyectado credenciales, el plugin asume `is_mock_mode() = True` por defecto si no detecta ninguna API key en el entorno (`GROQ_API_KEY`, etc.).
2. **Desacoplamiento Estricto Plugin / Runtime (AGENTS.md §4):**
   - El plugin no contiene dependencias rígidas ni asume la estructura interna del contenedor `jg_agente` (`app.core.llm`). Las factorías se resuelven dinámicamente tolerando cualquier fallo sin interrumpir las herramientas.
3. **Aislamiento de Prompts con XML:**
   - La biblia y los candados del autor se inyectan dentro de delimitadores XML explícitos (`<bible_characters>`, `<pinned_terms>`), instruyendo al modelo a procesarlos exclusivamente como datos inertes de referencia, previniendo secuestros del flujo o inyecciones adversarias.
4. **Metadatos Canónicos de Degradación:**
   - Se estandariza el reporte de `metadata["source"] = "agent" | "heuristic_fallback"` y `metadata["degraded"] = False | True` para alinear el contrato con el cliente consumidor en Laravel (`AgentClientService`).
