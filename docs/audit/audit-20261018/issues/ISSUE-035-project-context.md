# Project Context Update - ISSUE-035: Mitigación de ReDoS y Límites de Entrada

## Módulos Afectados
- `jg_plugin_filmmaker/tools.py`
- `jg_plugin_filmmaker/formatting.py`
- `jg_plugin_filmmaker/tests/test_redos_mitigation.py`
- `jg_filmmaker/app/Services/JgAgente/AgentClientService.php`
- `jg_filmmaker/tests/Feature/ReDosMitigationTest.php`

## Decisiones Técnicas y Arquitectura
1. **Límites Finitos de Longitud en Esquemas de Herramientas:**
   - La API del agente valida exhaustivamente los tamaños de los textos recibidos en `raw_text`, `structured_text` y `screenplay_text` (tope de 50.000 caracteres, equivalente a ~10-12 páginas de guion literario).
   - Las listas de contexto (`characters_context`, `locations`, `blocks`) tienen un techo finito para prevenir consumo desmesurado de memoria y tiempo en iteraciones.
2. **Eliminación de Catastrophic Backtracking en Parsing Narrativo:**
   - Se reemplazaron los cuantificadores perezosos universales `.*?` por conjuntos de negación acotados `[^.?!]{0,250}?` y límites numéricos `.{1,5000}`.
   - Se implementó un filtro preliminar (fast-path) con `\b<verbos>\b` que descarta instantáneamente el 90%+ de las líneas de acción que no contienen verbos de habla indirecta, evitando invocar el motor de backtracking en vano.
3. **Paridad de Implementación:**
   - La misma estrategia y cotas se mantuvieron idénticas entre la implementación nativa en Python (`formatting.py`) y el motor de fallback determinista de Laravel en PHP (`AgentClientService.php`).
