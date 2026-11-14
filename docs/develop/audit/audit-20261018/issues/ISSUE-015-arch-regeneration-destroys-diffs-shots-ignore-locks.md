# [ISSUE-015] Regeneración destruye `manual_diffs`/candados y el desglose de planos ignora `locked_fields`

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/ScriptPipelineService.php:84-96,171-183,199-266`, `app/Livewire/Studio/ScriptEditor.php:1009-1034`, `app/Models/ScriptBlock.php:118-129`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
- Al regenerar un tratamiento/guion (etapa desbloqueada), el pipeline **borra** del JSON todos los `manual_diffs` de esa etapa y su entrada en `locked_fields`. Tras desbloquear 🔓 y regenerar, el historial de ediciones del autor (único registro de qué cambió y por qué) desaparece sin snapshot. Solo la UI avisa "perderás tus cambios", pero no existe recuperación (el `UserActivityLog` no registra el contenido previo en `structure_block_ai`).
- `generateShotsBreakdown()` **no comprueba `isStageLocked('shots')`** ni usa lock alguno: cada ejecución hace `updateOrCreate` por `(scene_id, script_block_id, shot_number)` y **sobrescribe `shot_type` y `description`** editados a mano; además nunca elimina planos obsoletos, de modo que un nuevo desglose con menos planos deja planos huérfanos con tomas.
- `toggleStageLock($blockId, $stage)` no valida `$stage` (acepta cualquier cadena) y `isStageLocked('structured')` devuelve `true` si `screenplay` está bloqueado: desbloquear 'structured' mientras 'screenplay' está bloqueado no tiene efecto visible (estado confuso).
- `applyUserLocks()` aplica `str_replace($original, $override)` global sobre el texto IA: si `original_ai` es una cadena corta/común (p. ej. "él") reemplaza todas sus apariciones, no sólo el fragmento editado.

## 2. Evidencia de Código
```php
$userLocks['manual_diffs'] = array_values(array_filter($userLocks['manual_diffs'], fn ($diff) => ($diff['stage'] ?? null) !== 'structured'));
```
```php
public function generateShotsBreakdown(ScriptBlock $block): Collection
{
    ... Shot::updateOrCreate([... 'shot_number' => $shotNumber], ['shot_type' => $shotType, 'description' => $description]);
```

## 3. Impacto Operativo o de Seguridad
Pérdida irreversible de ediciones manuales y de metadatos de rodaje al regenerar; planos duplicados/huérfanos con material de rodaje vinculado.

## 4. Solución Recomendada
- Archivar (no borrar) diffs obsoletos: mover a `user_locks['archived_diffs']` con `archived_at`, o a tabla de versiones, y snapshot del texto previo en `UserActivityLog` antes de regenerar.
- Respetar `isStageLocked('shots')` y añadir un indicador `source` (`ai|manual`) en `shots`; sólo actualizar planos `source=ai` y marcar como "obsoletos" (soft state) los no devueltos en vez de borrarlos.
- Validar `$stage` con `in_array($stage, ['structured','screenplay','shots'], true)`.
- Sustituir `str_replace` por reemplazo anclado (posición/contexto) o diff real a nivel de párrafo.
- Tests: regenerar con candado → intacto; desbloquear y regenerar → snapshot recuperable.
