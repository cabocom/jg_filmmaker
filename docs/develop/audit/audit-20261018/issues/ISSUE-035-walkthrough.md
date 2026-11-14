# Walkthrough - ISSUE-035: Mitigación de ReDoS y Límites de Entrada en Python y PHP

## Resumen de la Solución Implementada
Se blindaron las herramientas de normalización y formateo tanto en `jg_plugin_filmmaker` (Python) como en `jg_filmmaker` (PHP) contra ataques de denegación de servicio por expresiones regulares (ReDoS) y desbordamientos por payloads desmesurados.

## Cambios Realizados

### 1. jg_plugin_filmmaker (Python)
- **`jg_plugin_filmmaker/tools.py`**:
  - Incorporadas restricciones de longitud en los esquemas Pydantic:
    - `TreatmentStructuringArgs`: `raw_text` limitado a `max_length=50000`, `characters_context` limitado a `max_length=100`.
    - `ScreenplayFormatterArgs`: `structured_text` limitado a `max_length=50000`, `characters_context` a `max_length=100`.
    - `ShotBreakdownDetectorArgs`: `screenplay_text` limitado a `max_length=50000`.
    - `PlotHoleDetectorArgs`: `blocks` acotado entre 1 y 200 elementos (`max_length=200`), y contextos de personajes/localizaciones a 100.
- **`jg_plugin_filmmaker/formatting.py`**:
  - Sustituidos cuantificadores abiertos `.*?` por cuantificadores acotados finitos con exclusión explícita de puntuación: `[^.?!]{0,250}?` para el sujeto/contexto previo, `[^.?!]{0,100}?` para modificadores de verbos y `.{1,5000}` para el cuerpo del diálogo.
  - Añadido límite de palabra `\b` alrededor de la alternancia de verbos declarativos.
  - Implementado fast-path previo con `verbs_re = re.compile(rf"\b{verbs}\b", re.IGNORECASE)` para descartar párrafos sin verbos antes de evaluar la regex compleja.
- **`tests/test_redos_mitigation.py`**:
  - Pruebas unitarias de rechazo en Pydantic ante payloads > 50.000 caracteres o listas desmedidas.
  - Prueba de rendimiento adversarial (< 0.5s en textos deliberadamente ambiguos).
  - Prueba de fast-path en textos largos sin verbos (< 0.1s).

### 2. jg_filmmaker (PHP / Laravel)
- **`app/Services/JgAgente/AgentClientService.php`**:
  - En el fallback determinista `executeDeterministicFallback`, sustituida la regex abierta por cuantificadores finitos acotados: `^([^.?!]{0,250}?)...` y `.{1,5000}$`.
  - Añadido chequeo preliminar `$hasVerb = (bool) preg_match('/\b'.$verbs.'\b/iu', $cleanPara)` para cortocircuitar oraciones sin verbos declarativos en O(1)/O(n).
- **`tests/Feature/ReDosMitigationTest.php`**:
  - Pruebas automatizadas de rendimiento ante cadenas de backtracking adversarial (< 0.3s).
  - Prueba de fast-path (< 0.1s).
  - Verificación de preservación de detección de diálogos indirectos.

## Verificación y Calidad
- **Python**: `bash scripts/check.sh` -> Ruff (0 errores) y 21 tests pasando en 0.11s.
- **PHP**: `./vendor/bin/pint --test` (Passed) y `php artisan test` (143 tests pasando, 686 aserciones, 0 errores).
