# Walkthrough de Implementación: ISSUE-018

## Resumen de Cambios Realizados

1. **Validación Estricta de Etapas y Estados en Livewire:**
   - En `ScriptEditor.php`:
     - `saveBlock()` y `saveBlockFromDrawer()` validan que `editStage` pertenezca a la lista blanca `['raw', 'structured', 'screenplay', 'shots', 'editing']` y aplican límite `max:50000` a `editRawText`, `editStructuredText` y `editScreenplayContent`.
     - `setBlockStage()` valida en tiempo de ejecución que el argumento `$stage` sea uno de los valores válidos antes de actualizar el modelo.
     - `setDetailTab()` valida contra la lista blanca `['raw', 'structured', 'screenplay', 'shots', 'diffs', 'locks']`.
     - `createNewBlock()` aplica límite estricto `max:50000` a `newBlockRawText`.
     - `saveTakeNotes()` valida límite `max:5000` en `editingTakeNotesText`.
   - En `ProjectList.php`:
     - Propiedad `$status` validada con regla `#[Rule('required|in:draft,in_progress,completed,archived')]`.

2. **Blindaje de Integridad en `ContextBibleDrawer`:**
   - Marcadas con `#[Locked]` las propiedades públicas de borrado: `$deletingType`, `$deletingId`, `$deletingName` y `$deletingUsages`, impidiendo manipulación de estado por parte del cliente.
   - Re-auditoría obligatoria de usos en servidor en `confirmDelete()`: se extrajeron los métodos `auditCharacterUsages()` y `auditLocationUsages()`. Aun si un atacante enviase `$deletingUsages = []`, el servidor recalcula las dependencias en la base de datos y aborta el borrado con error si el elemento está en uso.
   - Creación de personajes y localizaciones protegida con `Rule::unique(...)->where('project_id', $this->project->id)` para evitar errores 500 por clave duplicada a nivel SQL.
   - Límites de longitud `max:5000` en notas de arte, iluminación, biografías y psicología.

3. **Pruebas Automatizadas:**
   - Creado `tests/Feature/LivewireStateValidationTest.php` con 6 tests específicos verificando el rechazo de etapas inválidas, descarte de inyecciones en `setBlockStage`, límite de texto gigante, validación de estado en `ProjectList`, unicidad de nombres en la Biblia, y blindaje de re-auditoría en servidor en borrado.
