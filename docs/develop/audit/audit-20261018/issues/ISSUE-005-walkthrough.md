# Walkthrough: [ISSUE-005] Blindaje contra robo de bloques y desincronización en `reorderBlock()`

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se ha blindado completamente el método `reorderBlock` en `app/Livewire/Studio/ScriptEditor.php`:

1. **Aislamiento Multitenant Estricto:**
   - La escena destino se resuelve exclusivamente mediante `$this->projectScene($sceneTargetId)`.
   - El bloque arrastrado se resuelve exclusivamente mediante `$this->projectBlock($draggedBlockId)`.
   - Si un usuario malintencionado intenta arrastrar un bloque de otro proyecto a su propia escena, la solicitud arroja una excepción `ModelNotFoundException` (HTTP 404), impidiendo la exfiltración o robo de bloques.

2. **Reasignación Atómica de Planos Dependientes:**
   - Si el bloque cambia de escena (`$originSceneId !== $targetScene->id`), se actualiza atómicamente el campo `scene_id` de todos los planos asociados en la tabla `shots` para mantener la integridad referencial.

3. **Reindexación Bidireccional de Escenas:**
   - Se reordenan secuencialmente (1..N) los bloques de la escena de origen (`$originSceneId`) para eliminar cualquier hueco dejado por el traslado.
   - Se reordenan e inserta el bloque en la posición `$targetOrder` dentro de la escena destino (`$targetScene->id`).
   - Todo el proceso se ejecuta dentro de una transacción de base de datos (`DB::transaction`).

---

## 2. Modificaciones en el Código

### `app/Livewire/Studio/ScriptEditor.php`

```php
public function reorderBlock(string $draggedBlockId, int $targetOrder, ?string $sceneId = null): void
{
    $sceneTargetId = $sceneId ?? $this->selectedSceneId;
    if (! $sceneTargetId) {
        return;
    }

    $targetScene = $this->projectScene($sceneTargetId);

    DB::transaction(function () use ($draggedBlockId, $targetScene, $targetOrder) {
        $dragged = $this->projectBlock($draggedBlockId);
        $originSceneId = $dragged->scene_id;
        $hasChangedScene = ($originSceneId !== $targetScene->id);

        if ($hasChangedScene) {
            $dragged->update(['scene_id' => $targetScene->id]);

            Shot::where('script_block_id', $dragged->id)
                ->update(['scene_id' => $targetScene->id]);

            $originBlocks = ScriptBlock::where('scene_id', $originSceneId)
                ->orderBy('block_order')
                ->get();
            foreach ($originBlocks as $index => $b) {
                $b->update(['block_order' => $index + 1]);
            }
        }

        // Reindexación en escena destino con inserción en $targetOrder
        ...
    });
}
```

---

## 3. Pruebas Automatizadas

Se ha creado la suite `tests/Feature/BlockReorderCrossTenantIsolationTest.php` con 3 pruebas:
- `test_cannot_reorder_or_steal_block_from_another_project`: comprueba que enviar el ID de un bloque ajeno devuelve 404 y no altera la escena ni el orden de dicho bloque.
- `test_cannot_reorder_into_scene_of_another_project`: comprueba que intentar mover un bloque propio a una escena ajena devuelve 404.
- `test_moving_block_between_own_scenes_updates_dependent_shots_and_reindexes_both_scenes`: verifica la actualización de `scene_id` en `shots` y la compactación de `block_order` en ambas escenas.

Resultado: 3 tests pasando con 12 aserciones.
