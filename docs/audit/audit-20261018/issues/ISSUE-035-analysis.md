# Análisis - ISSUE-035: Mitigación de ReDoS y Límites de Entrada en el Plugin de Python y Normalizador PHP

## 1. Diagnóstico de la Situación Inicial

- **Argumentos Pydantic sin cota superior (`max_length`):** En `jg_plugin_filmmaker/tools.py`, los modelos `TreatmentStructuringArgs`, `ScreenplayFormatterArgs` y `ShotBreakdownDetectorArgs` únicamente exigían `min_length=1` en los campos de texto (`raw_text`, `structured_text`, `screenplay_text`), sin fijar ningún límite máximo. Tampoco se limitaban las listas `characters_context` ni `blocks`. Esto permitía el envío de payloads arbitrariamente masivos capaces de saturar la memoria o CPU de los workers de FastAPI / Uvicorn y disparar consumos desmesurados de tokens.
- **Backtracking no acotado en habla indirecta (`indirect_pattern`):** En `jg_plugin_filmmaker/formatting.py` (y su contraparte determinista en `AgentClientService.php`), la detección de habla indirecta empleaba un patrón con múltiples cuantificadores perezosos y alternancias solapadas (`^(.*?)(?:[,\s]+y\s+|\s+y\s+|[,\s]+)?...`). En párrafos extensos sin coincidencias exactas o con repeticiones de conjunciones, la complejidad temporal crecía exponencialmente (O(n³)), bloqueando el hilo de ejecución sin posibilidad de timeout nativo en Python.
- **Ausencia de chequeo de error en PCRE (PHP):** Si un texto alcanzaba `pcre.backtrack_limit`, PHP fallaba silenciosamente retornando false sin registro estructurado.

## 2. Plan de Solución

1. **Blindaje Pydantic v2 en `jg_plugin_filmmaker/tools.py`:**
   - Asignar `max_length=50000` a `raw_text`, `structured_text` y `screenplay_text`.
   - Asignar `max_length=100` a `characters_context` y `location_context` (campos string con máx. 500).
   - Asignar `max_length=200` a `blocks` en `PlotHoleDetectorArgs`.

2. **Reingeniería de Detección de Habla Indirecta en Python (`formatting.py`):**
   - Introducir un *fast-path check*: comprobar previamente si el párrafo contiene algún verbo de diálogo con `re.search(verbs, clean_p)`. Si no contiene ningún verbo, omitir de inmediato la evaluación de patrones complejos.
   - Reemplazar cuantificadores abiertos `.*?` por cuantificadores acotados de longitud finita (`[^.?!]{0,250}?` para cláusulas previas y `[^.?!]{0,100}?` para acotaciones de modo).

3. **Reingeniería equivalente en PHP (`AgentClientService.php`):**
   - Incorporar *fast-path check* de verbos antes del `preg_match` de habla indirecta.
   - Acotar cuantificadores en el regex y validar `preg_last_error()`.

4. **Pruebas y Verificación:**
   - Crear test de rendimiento adversarial en Python `tests/test_redos_mitigation.py` con 100 KB de texto con repetición de conjunciones, comprobando resolución en milisegundos sin bloqueo.
   - Crear test en PHP `tests/Feature/ReDosMitigationTest.php` validando entradas masivas y rechazo en Pydantic.
   - Validar linters (`ruff`, `pint`) y suites de tests al 100%.
