# [ISSUE-025] Ficheros de vídeo huérfanos: el borrado en cascada de BD no dispara `Take::deleting` ni limpia el disco

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Models/Take.php:48-55`, `app/Livewire/Studio/ScriptEditor.php:729-779`, `database/migrations/2026_10_09_200006_create_shots_table.php:16`, `..._200007_create_takes_table.php:16`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
La limpieza física del vídeo se implementa en el evento Eloquent `Take::deleting`. Pero las claves foráneas son `cascadeOnDelete()` a nivel de BD (`scenes → shots → takes`), por lo que al borrar una escena (que sólo exige no tener bloques; los planos manuales `script_block_id = null` y sus tomas sobreviven y se eliminan por cascada) o un proyecto (cascada desde `projects`) o un bloque (`nullOnDelete` en shots, que permanecen) **los eventos de modelo no se disparan** y los ficheros `media/projects/{id}/...` permanecen en disco indefinidamente. También ocurre al transferir/eliminar usuarios (`users → projects` cascade).
Además `Take::deleting` borra el fichero **antes** de confirmar el borrado de la fila (si el `DELETE` falla, queda una fila apuntando a un fichero inexistente) y no hay ninguna tarea de reconciliación.
Se suman los renders `rough_cut_*.mp4` (ISSUE-009) que nunca se eliminan.

## 2. Evidencia de Código
```php
static::deleting(function (Take $take): void {
    if ($take->file_path && Storage::disk('public')->exists($take->file_path)) {
        Storage::disk('public')->delete($take->file_path);
    }
});
```
```php
$table->foreignUuid('shot_id')->constrained('shots')->cascadeOnDelete();
```

## 3. Impacto Operativo o de Seguridad
Fuga de almacenamiento (los vídeos pesan hasta 100 MB cada uno) y retención de material de usuarios que creen haberlo eliminado (cumplimiento RGPD / derecho de supresión).

## 4. Solución Recomendada
- Borrar en servicio dedicado `MediaCleanupService::purgeProject/Scene/Shot` dentro de la transacción de borrado usando `DB::afterCommit`, o almacenar en `media_assets` y recolectar huérfanos con un Job programado (`storage:prune-orphans`).
- Alternativa más simple: eliminar el directorio `media/projects/{id}` al borrar proyecto/escena (`Storage::deleteDirectory`).
- Usar `afterCommit` en el evento `deleted` en vez de `deleting`.
- Comando `php artisan media:reconcile` + test.
