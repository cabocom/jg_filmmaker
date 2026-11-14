# Análisis - ISSUE-016: Sanitización de Backreferences y Lookarounds en `pinned_terms` (PHP y Python) y Creación en UI

## 1. Diagnóstico de la Situación Inicial

- **Interpretación no intencionada de Backreferences:** Tanto en `AgentClientService::applyUserLocks()` (PHP) como en `structuring::apply_user_locks()` (Python), se utiliza `preg_replace($pattern, (string) $replacement, $result)` y `re.sub(pattern, str(replacement), result)`. Ambos motores tratan el string de reemplazo como una plantilla que evalúa referencias de captura (`$1`, `\1`, `\g<1>`, `${name}`). Si un autor fija un término que contenga estas secuencias (e.g., `Precio: $10`, `Modelo \1`, `AT&T`), en PHP se generan sustituciones truncadas o corruptas, y en Python se desencadena `re.error: invalid group reference`, provocando que la herramienta falle con `success: false`.
- **Incompatibilidad de `\b` con términos no alfanuméricos:** La frontera de palabra `\b` exige una transición entre carácter de palabra (`\w`) y no-palabra. Si un término empieza o termina con símbolos (e.g. `C++`, `#1`, `"El Padrino"`), la expresión `\bC++\b` jamás coincide en el texto.
- **Ausencia de límites de complejidad:** No existen topes para el número de términos ni para su longitud por bloque, lo que expone a DoS por múltiples escaneos regex sobre textos extensos.
- **Carencia de interfaz de creación (`ScriptEditor`):** `ScriptEditor.php` solo disponía del método `removePinnedTerm()`, careciendo de un método `addPinnedTerm()` y de un formulario en la vista para que el usuario pueda fijar términos desde la aplicación sin recurrir a la base de datos o tests.

## 2. Plan de Solución

1. **Corrección en PHP (`app/Services/JgAgente/AgentClientService.php`):**
   - Reemplazar `preg_replace` por `preg_replace_callback($pattern, fn () => (string) $replacement, $result)`, impidiendo cualquier expansión de backreferences.
   - Usar límites con lookarounds adaptativos:
     - Si el primer carácter es alfanumérico/palabra (`\w` con flag `u`), anteponer `(?<![\p{L}\p{N}_])`.
     - Si el último carácter es alfanumérico/palabra, posponer `(?![\p{L}\p{N}_])`.
     - Si no lo son, delimitar exactamente sin romper términos con símbolos como `C++`.
   - Limitar el procesamiento a un máximo de 50 términos por bloque y 80 caracteres por término/reemplazo.

2. **Corrección en Python (`jg_plugin_filmmaker/structuring.py`):**
   - Reemplazar `re.sub(pattern, str(replacement), ...)` por:
     `re.sub(pattern, lambda _m, r=str(replacement): r, result, flags=re.IGNORECASE)`
   - Emplear lookarounds adaptativos con `(?<![\w])` y `(?![\w])` según corresponda con soporte Unicode.
   - Limitar términos a máx. 50 y longitudes a máx. 80 caracteres.

3. **Incorporación de Creación en UI (`ScriptEditor.php` y Blade):**
   - Implementar `addPinnedTerm(string $blockId, string $term, string $replacement): void` en `ScriptEditor.php` con validación de tamaño (máx. 80 caracteres) y conteo (máx. 50).
   - Añadir propiedades reactivas `$newPinnedTerm` y `$newPinnedReplacement` para entrada directa desde el Drawer de detalles del bloque.
   - Añadir formulario en `script-editor.blade.php` para fijar nuevos términos.

4. **Verificación y Pruebas Automatizadas:**
   - Crear test en PHP `tests/Feature/PinnedTermsSafetyTest.php` verificando términos con `$1`, `\1`, `C++`, límites de 80 chars y 50 términos, y método Livewire `addPinnedTerm`.
   - Crear test en Python `tests/test_pinned_terms_backrefs.py` verificando términos con `$1`, `\1`, `C++` en `jg_plugin_filmmaker`.
   - Ejecutar suites completas en ambos repositorios (`php artisan test`, `pytest`) y linters (`pint`, `ruff`).
