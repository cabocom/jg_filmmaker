# [ISSUE-013] Pérdida de `user_locks` por actualización perdida (read-modify-write sin control de concurrencia alrededor de llamadas IA de 30-45 s)

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/ScriptPipelineService.php:53-102,141-189,243-263`, `app/Livewire/Studio/ScriptEditor.php:323-348,888-927,1009-1034,1061-1070,1703-1722`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
Los candados del usuario (`pinned_terms`, `manual_diffs`, `locked_fields`) viven en una única columna JSON `script_blocks.user_locks`. Todas las operaciones leen el JSON, lo modifican en memoria y lo reescriben completo:

- `transitionToStructured/Screenplay` leen `resolveUserLocks()` **antes** de la llamada al agente (hasta 30 s de timeout + posible LLM directo de 45 s) y escriben `'user_locks' => $userLocks` **después**. Cualquier candado añadido/retirado en ese intervalo (otra pestaña, otro dispositivo, `toggleStageLock`, `removePinnedTerm`) se pierde.
- `analyzePlotHoles()` carga todos los bloques al inicio y al final hace `update(['user_locks' => $userLocks])` para **cada bloque del proyecto** con el JSON viejo, tras minutos de auditoría en 3 fases: puede deshacer candados y justificaciones del autor en todo el guion.
- `saveBlock/saveBlockFromDrawer` comparan el texto de los campos de edición (cargados al abrir el drawer) con el actual y llaman `recordManualOverride`: si la IA o otra pestaña regeneró el texto entre medias, se registra como "edición manual" el texto IA antiguo y **se bloquea la etapa** con un diff falso.
- Sin `lockForUpdate`, sin versión optimista (`updated_at`/`lock_version`), sin transacción.

Esto rompe la garantía documentada de inmutabilidad de los bloqueos ante re-ejecuciones de IA (`docs/03_user_locks_governance.md`).

## 2. Evidencia de Código
```php
$userLocks = $this->resolveUserLocks($block);       // lectura
$structuredText = $this->agentClient->structureTreatment(... userLocks: $userLocks);   // 30-75 s
...
$block->update(['structured_text' => $structuredText, 'user_locks' => $userLocks, 'stage' => 'structured']);  // escritura con JSON obsoleto
```

## 3. Impacto Operativo o de Seguridad
Pérdida silenciosa de términos fijados, candados y justificaciones; sobreescritura de texto aprobado por el autor tras regenerar (trabajo creativo perdido), estados inconsistentes bloqueados por diffs falsos.

## 4. Solución Recomendada
- Re-leer bajo bloqueo justo antes de persistir y fusionar sólo las claves que el proceso modifica:
```php
DB::transaction(function () use ($block, $structuredText, $telemetry) {
    $fresh = ScriptBlock::whereKey($block->id)->lockForUpdate()->first();
    $locks = $this->resolveUserLocks($fresh);
    $locks = $this->mergeAiTelemetry($locks, 'structured', $telemetry);   // solo claves propias
    $fresh->update(['structured_text'=>$structuredText,'user_locks'=>$locks,'stage'=>'structured']);
});
```
- Alternativa estructural: tablas normalizadas (`block_locks`, `block_pinned_terms`, `block_manual_diffs`) para que cada operación sea un `INSERT/DELETE` atómico.
- En `analyzePlotHoles`, actualizar sólo `plot_hole` y `plot_hole_stage` con `UPDATE ... SET` JSON parcial (o columnas dedicadas), nunca el JSON completo.
- Campo `lock_version` (optimistic locking) en edición de texto: rechazar el guardado si la versión cambió.
- Comprobar `isStageLocked()` **dentro** de la transacción y tras la llamada IA (re-check) para no sobrescribir un texto que el usuario bloqueó mientras tanto.
- Tests concurrentes simulados (modificar el bloque durante el `Http::fake` callback).
