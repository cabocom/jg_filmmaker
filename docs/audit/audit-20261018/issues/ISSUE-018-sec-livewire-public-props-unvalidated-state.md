# [ISSUE-018] Propiedades públicas Livewire manipulables y valores de `stage`/`tab`/`stage-lock` sin validar

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:29-189,339-344,905-910,1009-1027,1400-1404`, `app/Livewire/Projects/ProjectList.php:19-24`, `app/Livewire/Studio/ContextBibleDrawer.php:62-72,236-244`
- **Categoría:** Seguridad

## 1. Descripción del Problema
Las más de 60 propiedades públicas de `ScriptEditor` (y de `ContextBibleDrawer`, `RoughCutTimeline`, `Login`) son modificables por el cliente: ninguna usa `#[Locked]`. Se usan como entrada de confianza:

- `editStage` (`saveBlock`, `saveBlockFromDrawer`) y el argumento `$stage` de `setBlockStage()` se escriben tal cual en la columna `stage` (`enum raw|structured|screenplay|shots|editing`): valores inválidos producen `QueryException` 500 (violación del enum/CHECK). `ProjectList::$status` tampoco se valida (`draft` por defecto, sin `in:`).
- `toggleStageLock($blockId, $stage)` añade cualquier cadena a `locked_fields` (inyección de estados arbitrarios).
- `setDetailTab`, `setActiveView`, `setPipelineStage`, `setScenePipelineStage`: sin lista blanca.
- `ContextBibleDrawer::$deletingUsages` es una propiedad pública que `confirmDelete()` usa como **guardia de integridad** ("no se puede eliminar: está en uso"). Un cliente la fija a `[]` y borra personajes/localizaciones usados en el guion.
- Textos sin `max` (`editRawText`, `editStructuredText`, `editScreenplayContent`, `newBlockRawText`, `editingTakeNotesText`): permiten payloads de megabytes (límite real = `post_max_size` 512 MB) que además se reenvían al LLM.
- Los `createCharacter/createLocation` no validan duplicados: `unique(project_id,name)` produce excepción 500 y la comparación en pipeline usa `LOWER(name)` (distinto criterio).

## 2. Evidencia de Código
```php
$block->update(['stage' => $this->editStage, ...]);                   // editStage controlado por cliente
$lockedFields[] = $stage;                                              // sin validar
if (! empty($this->deletingUsages)) { ...bloquear... }                // guardia en propiedad del cliente
```

## 3. Impacto Operativo o de Seguridad
Corrupción de estado de bloques, bypass de reglas de integridad referencial de negocio, errores 500 y consumo excesivo de recursos/tokens.

## 4. Solución Recomendada
- `#[Locked]` en todo ID/estado que no deba cambiar desde el cliente; `#[Validate]`/`Rule::enum` con un Enum PHP `BlockStage` y `$this->validate()` en cada acción.
- Recalcular `usages` en `confirmDelete()` en servidor (no confiar en la propiedad).
- Límites `max:20000` por texto y `max:120` por campos cortos.
- Validar duplicados con `Rule::unique('characters','name')->where('project_id', ...)` (case-insensitive).
- CHECK constraint o cast a Enum en el modelo.
