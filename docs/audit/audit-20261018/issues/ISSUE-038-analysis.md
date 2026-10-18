# Análisis - ISSUE-038: Manejo Seguro de Diálogos Vacíos o Puntuación Residual en refine_dialogue_to_direct_speech

## 1. Situación Inicial
- En `jg_plugin_filmmaker/formatting.py:79,82`, la función `refine_dialogue_to_direct_speech` procesa verbos como `pregunta` y `grita` haciendo:
  ```python
  return f"¿{res.strip('.?!')[0].upper() + res.strip('.?!')[1:]}?"
  return f"¡{res.strip('.?!')[0].upper() + res.strip('.?!')[1:]}!"
  ```
- Si `res` queda vacío tras purgar la puntuación (por ejemplo, si el texto original era "pregunta que ?" o "grita que ..."), `res.strip('.?!')` produce la cadena vacía `""`. Acceder a `[0]` provoca un `IndexError`.
- Este `IndexError` no controlado aborta toda la ejecución de `screenplay_formatter`, degradando la conversión del bloque completo por un único diálogo mal formado.
- Además, en PHP (`AgentClientService.php`), oraciones vacías o compuestas únicamente de signos generaban `¿?` o `¡!` sin puntuación de elipsis o fallback.

## 2. Solución Propuesta

### A. Corrección en `jg_plugin_filmmaker/formatting.py`
Extraer el núcleo del diálogo mediante `core = res.strip(".?!¿¡ ")`.
- Si `core` está vacío, devolver un fallback seguro (p. ej. `"¿...?"` para pregunta, `"¡...!"` para grito, o `res.strip() or "..."`).
- Si `core` tiene contenido, capitalizar el primer carácter una sola vez (`core[0].upper() + core[1:]`) y envolverlo entre los signos correspondientes (`¿...?` o `¡...!`).
- Normalizar de forma idéntica en la iteración general de oraciones para evitar indexar cadenas vacías.

### B. Paridad en `jg_filmmaker/app/Services/JgAgente/AgentClientService.php`
- Aplicar `trim($res, ".?!¿¡ \t\n\r\0\x0B")` para asegurar que cadenas vacías o con solo puntuación devuelvan una elipsis consistente (`¿...?`, `¡...!`) en lugar de signos vacíos.

### C. Pruebas Unitarias
- Crear tests específicos en Python (`tests/test_refine_dialogue_safety.py`) probando entradas como `""`, `"?"`, `"..."`, `"que ?"`, `"le pregunta qué..."`, `"grita que !"` sin ningún `IndexError`.
- Añadir aserciones en PHP para garantizar la misma robustez.
