# Análisis - ISSUE-042: Cobertura Exhaustiva de Tests y Validación de Paridad de Contrato en jg_plugin_filmmaker

## 1. Situación Inicial
- La suite inicial de pruebas del plugin contaba únicamente con ~13 pruebas básicas en `test_filmmaker_tools.py`, enfocadas exclusivamente en caminos felices.
- Aunque en los issues anteriores se agregaron suites para backreferences (`test_pinned_terms_backrefs.py`), ReDoS (`test_redos_mitigation.py`), tipado Pydantic (`test_pydantic_strict_typing.py`), seguridad de diálogos (`test_refine_dialogue_safety.py`) y resiliencia de LLM (`test_llm_resilience_and_mock.py`), existía una brecha en la verificación de paridad contractual bidireccional entre Laravel (`AgentClientService`) y el plugin de Python.
- No existía un conjunto de vectores de prueba dorados (golden contract fixtures) compartidos que certifiquen que los esquemas generados por Eloquent (`characters_context` con `role`, `personality_notes`, `user_locks` con `pinned_terms`, `manual_diffs` y `archived_diffs`) sean procesados idénticamente por ambos componentes sin divergencias silenciosas.

## 2. Solución Propuesta

### A. Golden Vectors de Contrato Compartido
Crear un archivo de vectores JSON canónicos (`tests/fixtures/contract_golden_vectors.json`) que contenga:
1. Vector `treatment_structuring` con contexto de biblia de Laravel (personajes con `role`, `bio`, `psychology`, escenario con `lighting_notes`, `pinned_terms` complejos).
2. Vector `screenplay_formatter` con mezcla de acción, habla indirecta compleja, acotaciones y candados.
3. Vector `shot_breakdown_detector` con escenas de múltiples párrafos y tipologías canónicas de plano.
4. Vector `plot_hole_detector` con bloques estructurados por Laravel (`uuid`, `scene_id`, `type`, `content`, `character`).

### B. Suite de Pruebas de Paridad en Python (`tests/test_contract_parity.py`)
- Cargar los vectores dorados y validar que las 4 herramientas ejecuten con éxito, respeten los candados, generen metadatos consistentes y reporten `source` y `degraded`.

### C. Suite de Pruebas de Paridad en PHP (`tests/Feature/ContractParityTest.php`)
- Validar que `AgentClientService` ejecute los mismos vectores dorados contra el fallback determinista produciendo los mismos resultados esperados, garantizando la paridad total sin roturas en producción.

### D. Aumento de Cobertura y Blindaje de Red
- En `conftest.py`, reforzar la protección para que no se permita ninguna conexión de red saliente hacia LLMs reales durante la ejecución de los tests.
