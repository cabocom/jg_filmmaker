# [ISSUE-005] `reorderBlock()` permite mover bloques de otros proyectos a una escena propia (robo/destrucción de guion)

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:415-461`
- **Categoría:** Seguridad

## 1. Descripción del Problema
`reorderBlock(string $draggedBlockId, int $targetOrder, ?string $sceneId)` acepta tres valores controlados por el cliente. Busca el bloque con `ScriptBlock::find($draggedBlockId)` (global) y, si su `scene_id` difiere, ejecuta `update(['scene_id' => $sceneTargetId])` con un `$sceneTargetId` igualmente no validado. Un atacante puede (a) **robar** un bloque ajeno moviéndolo a su escena (y leer su contenido), o (b) **vaciar** el proyecto de la víctima moviendo sus bloques a una escena propia o inexistente (FK `cascade`/violación). Los `shots` enlazados conservan `scene_id` del proyecto original → corrupción referencial entre proyectos.

## 2. Evidencia de Código
```php
$dragged = ScriptBlock::find($draggedBlockId);
...
if ($dragged->scene_id !== $sceneTargetId) {
    $dragged->update(['scene_id' => $sceneTargetId]);   // sin comprobar proyecto de origen ni destino
```

## 3. Impacto Operativo o de Seguridad
Pérdida de trabajo creativo ajeno, exfiltración de contenido y corrupción de integridad entre tenants. No deja rastro en el log de la víctima (el `logActivity` se registra en el proyecto del atacante).

## 4. Solución Recomendada
```php
$dragged = ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))->findOrFail($draggedBlockId);
$targetScene = $this->project->scenes()->findOrFail($sceneTargetId);
```
Si se mueve entre escenas, reasignar también `shots.scene_id` de los planos del bloque dentro de la misma transacción y reindexar ambas escenas de origen y destino. Añadir test: mover bloque de otro proyecto → 404 y sin cambios en BD.
