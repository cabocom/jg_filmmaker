# [ISSUE-042] Cobertura insuficiente en el plugin: 13 tests sin casos de error, límites, locks adversariales ni contrato con el cliente PHP

- **Severidad:** Media
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `tests/test_plugin.py`, `tests/test_filmmaker_tools.py`, `.gitlab-ci.yml`, `jg_plugin_filmmaker/llm.py:17-24`
- **Categoría:** Calidad de Código

## 1. Descripción del Problema
- La suite contiene ~13 funciones de test que verifican caminos felices (`Javi → Javier`, presencia de `--- shot 1 (`). No hay casos para: `user_locks` malformados (lista en vez de dict), `pinned_terms` con `\1`/`$1` (ISSUE-016), texto vacío/gigante (ISSUE-035), personajes con caracteres regex, `manual_diffs` con `original_ai` repetido, diálogos vacíos (ISSUE-038), errores de LLM (excepción, `None`, timeout) ni modo degradado.
- No existe test que ejecute el **camino LLM** con un `llm_client` falso determinista para verificar que `apply_user_locks` se aplica a la salida del LLM y que el prompt no incluye datos no escapados.
- Sin test de contrato con el cliente PHP: ni vectores JSON compartidos ni verificación de las claves que Laravel envía (`personality_notes`, `role`) frente a las que el plugin lee (`bio`) (ISSUE-012); tampoco cubre `plot_hole_detector` (ISSUE-011).
- El aislamiento "cero tokens" depende de `PYTEST_CURRENT_TEST` (ISSUE-037); no hay `conftest.py` que lo fije explícitamente ni un test que garantice que, sin red y sin clave, ninguna ruta intenta una llamada externa.
- CI: `ruff check`/`ruff format --check` y `pytest`, pero sin `mypy`/`pyright`, sin cobertura mínima (`--cov-fail-under`), sin `pip-audit`.
- `pytest-asyncio` con `asyncio_mode="auto"` pero sólo un test asíncrono.

## 2. Evidencia de Código
```python
res_struct = runner.run_tool("treatment_structuring", {"raw_text": "Javi entra al bar.", "user_locks": {"pinned_terms": {"Javi": "Javier"}}})
assert "Javier" in res_struct.output
```

## 3. Impacto Operativo o de Seguridad
Regresiones de contrato y de seguridad (ReDoS, backrefs, fallos silenciosos) llegan a producción sin alarma; coste de tokens no controlado en entornos sin pytest.

## 4. Solución Recomendada
- `tests/conftest.py` con fixture autouse: `monkeypatch.setenv("JG_AGENTE_MOCK", "true")` y bloqueo de sockets (`pytest-socket`/`pytest-recording`).
- Tests parametrizados con `hypothesis` para `apply_user_locks` y el parser de planos.
- Golden files compartidos (`contracts/*.json`) validados en ambos repositorios.
- CI: `mypy --strict`, `pytest --cov=jg_plugin_filmmaker --cov-fail-under=85`, `pip-audit`.
