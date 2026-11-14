# [ISSUE-038] `refine_dialogue_to_direct_speech` lanza `IndexError` con diálogos vacíos tras purgar signos

- **Severidad:** Baja
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/formatting.py:73-82`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
Para los verbos `pregunta` y `grita` se calcula `res.strip('.?!')[0]`. Si el diálogo resultante tras la limpieza queda vacío o sólo contiene `.`, `?`, `!` (p. ej. "pregunta que ?"), `strip` devuelve `''` y `[0]` produce `IndexError`. La excepción se captura en `execute_screenplay_formatter` (`except Exception`) y **toda la herramienta devuelve `success=False`** para el bloque entero por un único párrafo mal formado. Además la expresión se evalúa dos veces (`.strip(...)[0]` y `[1:]`).

## 2. Evidencia de Código
```python
if "pregunta" in verb:
    ...
    return f"¿{res.strip('.?!')[0].upper() + res.strip('.?!')[1:]}?"
if "grita" in verb:
    return f"¡{res.strip('.?!')[0].upper() + res.strip('.?!')[1:]}!"
```

## 3. Impacto Operativo o de Seguridad
Fallo completo de la conversión a guion en modo heurístico/offline ante texto de usuario corriente; el cliente PHP lo oculta y cae a su propio fallback (ISSUE-010).

## 4. Solución Recomendada
```python
core = res.strip(".?!¿¡ ")
if not core:
    return res
core = core[0].upper() + core[1:]
return f"¿{core}?" if "pregunta" in verb else f"¡{core}!"
```
Añadir tests parametrizados (`"", "?", "...", "que ?"`).
