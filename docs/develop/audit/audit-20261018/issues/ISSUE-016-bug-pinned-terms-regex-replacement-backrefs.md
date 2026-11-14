# [ISSUE-016] `pinned_terms`: el reemplazo se interpreta como expresión regular (backreferences) en PHP y Python, y no hay UI para crearlos

- **Severidad:** Media
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `app/Services/JgAgente/AgentClientService.php:337-342`, `jg_plugin_filmmaker/structuring.py` (`apply_user_locks`), `app/Livewire/Studio/ScriptEditor.php:1061-1070`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
1. `preg_replace($pattern, (string) $replacement, $result)` (PHP) y `re.sub(pattern, str(replacement), ...)` (Python) tratan `$replacement` como **plantilla**: `$1`, `\1`, `${name}`, `\g<name>` se interpretan. Un término fijado como `AT&T \1` o `Pre$10` provoca sustituciones erróneas o, en Python, `re.error: invalid group reference` (el plugin lo captura con `except Exception` y devuelve fallo de la herramienta completa).
2. `\b` se aplica sin considerar que el término puede empezar/terminar con caracteres no-palabra (`C++`, `#1`, `"Ana"`), por lo que nunca coincide.
3. No hay validación del tamaño/número de términos ni de la longitud: un `pinned_terms` con miles de entradas multiplica el coste (regex por término sobre el texto completo, dos veces: en Laravel y en el plugin).
4. El código sólo contiene `removePinnedTerm()` y lectura (`ScriptEditor.php:1229`): **no existe ruta de creación** de `pinned_terms` en la aplicación (sólo tests o edición de BD). La función está documentada como mecanismo principal de gobernanza pero no es utilizable por el autor.

## 2. Evidencia de Código
```php
$pattern = '/\b'.preg_quote((string) $term, '/').'\b/iu';
$result = preg_replace($pattern, (string) $replacement, $result);      // $replacement con backrefs
```
```python
result = re.sub(pattern, str(replacement), result, flags=re.IGNORECASE)   # idem
```

## 3. Impacto Operativo o de Seguridad
Corrupción del texto del autor con términos "protegidos", errores 500/`success:false` del plugin, y funcionalidad de gobernanza incompleta.

## 4. Solución Recomendada
```php
$result = preg_replace_callback($pattern, fn () => (string) $replacement, $result);
```
```python
result = re.sub(pattern, lambda _m, r=str(replacement): r, result, flags=re.IGNORECASE)
```
- Lookarounds en lugar de `\b`: `(?<![\w])term(?![\w])` con `u` flag.
- Límites: máx. 50 términos, 80 caracteres por término/reemplazo, validados en servidor y en Pydantic.
- Implementar UI/acción `addPinnedTerm()` con validación, o retirar la función de la documentación hasta que exista.
- Tests con términos `$1`, `\1`, `C++`.
