# [ISSUE-040] `user_activity_logs` almacena snapshots completos del guion sin retención ni límite de tamaño

- **Severidad:** Baja
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:786-802,913-964,995-1004,1757-1771`, `database/migrations/2026_10_11_000001_create_user_activity_logs_table.php:22`, `docs/TODO_HISTORIA_EVOLUCION.md`
- **Categoría:** Rendimiento

## 1. Descripción del Problema
`logActivity()` guarda en `snapshot` (JSON) el `raw_text`, `structured_text` y `screenplay_content` completos en cada edición, estructuración, formateo y borrado, duplicando el contenido (incluso cuando una acción IA no cambió nada) y, en la auditoría de agujeros, `block_preview` y razones. No hay límite de tamaño, deduplicación (hash), política de retención ni `prune`. El modal de historial carga `latest()->take(100)` con `with('user')` incluyendo el JSON completo. Consecuencias: crecimiento sin límite de la tabla (y del fichero SQLite, ISSUE-032), exposición adicional de contenido creativo en una tabla de auditoría, imposibilidad de ejercer el derecho de supresión (el guion "borrado" persiste en los snapshots, cuya FK `user_id` es `cascade` pero `project_id` es `nullOnDelete`: tras borrar un proyecto los snapshots quedan huérfanos con contenido íntegro).
Además `logActivity` usa `auth()->user() ?? $this->project->user`, atribuyendo acciones al propietario cuando no hay usuario autenticado.

## 2. Evidencia de Código
```php
UserActivityLog::create([... 'snapshot' => $snapshot]);   // con raw_text, structured_text, screenplay_content completos
```
```php
$table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
```

## 3. Impacto Operativo o de Seguridad
Crecimiento de BD, retención de datos personales/creativos tras el borrado y coste en la carga del historial.

## 4. Solución Recomendada
- Guardar snapshots sólo cuando cambie el contenido (comparar hash), comprimir (`gzcompress`) o mover a tabla `block_versions` con retención (p. ej. 50 versiones por bloque).
- `model:prune` con `Prunable` (p. ej. 180 días) y cascada al borrar proyecto (`cascadeOnDelete` para `project_id` o purga explícita).
- Seleccionar columnas sin `snapshot` para el listado y cargarlo bajo demanda.
- Atribuir siempre a `auth()->id()` (rechazar si no hay usuario).
