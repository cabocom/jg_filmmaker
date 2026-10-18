# [ISSUE-023] Numeración (`block_order`, `scene_order`, `shot_number`, `take_number`) calculada con `max()+1` sin restricción única ni transacción

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:380-399,415-461,481-566,1425-1473`, `database/migrations/2026_10_09_200004_create_scenes_table.php:24`, `..._200005_create_script_blocks_table.php:26`, `..._200006_create_shots_table.php:25`, `..._200007_create_takes_table.php:25`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
Todos los contadores se obtienen con `max('...') + 1` o `increment()` por separado, y los índices de `(scene_id, block_order)`, `(project_id, scene_order)`, `(scene_id, shot_number)`, `(shot_id, take_number)` son **índices normales, no únicos**. Dos acciones simultáneas (doble clic, dos pestañas, drag&drop rápido) crean `shot_number`/`take_number`/`block_order` duplicados. `createNewBlock` incrementa los bloques posteriores y crea el nuevo **fuera de una transacción**; `reorderBlock` actualiza fila a fila dentro de transacción pero sin `lockForUpdate`. `addShot` fija `resolution: 3840x2160`, `codec: prores422hq` siempre.
El desglose de planos usa `updateOrCreate` por `(scene_id, script_block_id, shot_number)`: si dos bloques de la misma escena generan `shot 1`, no choca porque incluye `script_block_id`, pero la numeración visible por escena queda duplicada (varios "Plano 1").

## 2. Evidencia de Código
```php
$maxNumber = $scene->shots()->max('shot_number') ?? 0;
$shot = $scene->shots()->create(['shot_number' => $maxNumber + 1, ...]);
...
$maxTakeNumber = $shot->takes()->max('take_number') ?? 0;
```

## 3. Impacto Operativo o de Seguridad
Orden de montaje ambiguo (`orderBy scene_order, shot_number, take_number` del timeline y del ensamblado FFmpeg): rough cut con tomas en orden incorrecto o duplicadas; reportes de rodaje inconsistentes.

## 4. Solución Recomendada
- Restricciones `unique(['scene_id','block_order'])`, `unique(['project_id','scene_order'])`, `unique(['shot_id','take_number'])` (con reasignación en dos pasos/`DEFERRABLE` o valores temporales negativos al reordenar).
- Asignación dentro de `DB::transaction` con `lockForUpdate()` sobre el padre y reintento ante `UniqueConstraintViolationException`.
- `shot_number` por escena con política clara (global por escena vs por bloque).
- Tests de concurrencia simulada llamando dos veces seguidas a `addShot` con el mismo estado.
