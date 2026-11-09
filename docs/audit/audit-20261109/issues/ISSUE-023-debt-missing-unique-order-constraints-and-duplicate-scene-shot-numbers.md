# [ISSUE-023] Ausencia de Restricciones `UNIQUE` en Contadores de Orden y Duplicación de `shot_number` entre Bloques de una Escena (Pendiente de `audit-20261018 / ISSUE-023`)

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/ScriptPipelineService.php:297-314`, `jg_filmmaker/database/migrations/2026_10_09_200004_create_scenes_table.php:24`, `2026_10_09_200005_create_script_blocks_table.php:26`, `2026_10_09_200006_create_shots_table.php:25`, `2026_10_09_200007_create_takes_table.php:25`
- **Categoría:** Deuda Técnica

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-023`) se reportaron dos problemas estructurales de numeración que solo fueron resueltos parcialmente (se añadieron transacciones con `lockForUpdate()`, pero quedaron pendientes las restricciones de base de datos y la numeración de planos por escena):

1. **Colisión de `shot_number` dentro de una Misma Escena con Múltiples Bloques:**
   - En `ScriptPipelineService::generateShotsBreakdown(ScriptBlock $block)` (línea 298), el número de plano se calcula como `$shotNumber = $index + 1` (reiniciando siempre en `1` para cada bloque) y se busca con `where('scene_id', $block->scene_id)->where('script_block_id', $block->id)->where('shot_number', $shotNumber)`.
   - Como consecuencia, si una escena tiene 3 bloques y se desglosan los planos de cada bloque, la escena acaba con **tres planos llamados "Plano 1" (`shot_number = 1`), tres llamados "Plano 2" (`shot_number = 2`)**, etc.
2. **Índices No Únicos en Base de Datos:**
   - Los índices `(project_id, scene_order)` en `scenes`, `(scene_id, block_order)` en `script_blocks`, `(scene_id, shot_number)` en `shots` y `(shot_id, take_number)` en `takes` siguen siendo índices ordinarios (`$table->index(...)`) sin restricción `UNIQUE`.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/ScriptPipelineService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php#L297-L314):
```php
            foreach ($parsedShots as $index => $shotData) {
                $shotNumber = $index + 1;
                $shotType = $shotData['shot_type'] ?? 'PM';
                $description = $shotData['description'] ?? '';

                $existingShot = Shot::where('scene_id', $block->scene_id)
                    ->where('script_block_id', $block->id)
                    ->where('shot_number', $shotNumber)
                    ->first();
```

## 3. Impacto Operativo o Riesgo
- **Nomenclatura Técnica de Rodaje Ambigua:** En cinematografía y en la exportación de claquetas/montaje (`RoughCutTimeline`), los planos de una escena se numeran correlativamente a nivel de escena (`Escena 1 - Plano 1, 2, 3...`) o con sufijo de bloque, nunca teniendo múltiples `Plano 1` indistinguibles dentro de la misma escena.

## 4. Solución o Mejora Recomendada
1. En `ScriptPipelineService::generateShotsBreakdown()`, tras actualizar los planos del bloque, ejecutar una renumeración correlativa a nivel de escena (`resequenceSceneShots(Scene $scene)`) ordenada por `block_order` del bloque padre y posición del plano, garantizando que `shot_number` sea único y continuo (`1..M`) en toda la escena.
2. Añadir índices únicos diferibles (o sobre `(shot_id, take_number)`) una vez normalizada la secuencia.

