# Walkthrough Técnico - ISSUE-013: Control de Concurrencia y Prevención de Carreras en `user_locks`

## 1. Resumen de Cambios
Se ha implementado una arquitectura de concurrencia combinada (Pesimista y Optimista) para blindar el ciclo de vida de los candados del autor (`user_locks`) y la consistencia del guion frente a peticiones concurrentes y latencias prolongadas de inferencia de IA.

## 2. Componentes Modificados

### A. Base de Datos y Modelo `ScriptBlock`
- **Migración (`2026_10_11_000003_add_lock_version_to_script_blocks_table.php`):**
  - Añadida la columna `lock_version` de tipo entero sin signo (default: 1) para control de versiones optimista.
- **Modelo `ScriptBlock` (`app/Models/ScriptBlock.php`):**
  - Añadido `lock_version` a `$fillable`, `$casts` (integer) y atributos por defecto.
  - Implementado el método atómico `mutateUserLocks(callable $mutator): self`, que envuelve la mutación en `DB::transaction()` con `lockForUpdate()`, recarga el registro en fresco, aplica el mutador, incrementa `lock_version` y persiste de forma aislada.

### B. Pipeline de IA (`ScriptPipelineService.php`)
- **`transitionToStructured` y `transitionToScreenplay`:**
  - La llamada HTTP hacia el agente de IA se mantiene fuera de la transacción para evitar retener bloqueos en la base de datos durante los 30-75 s de inferencia.
  - Al recibir la respuesta, se abre una transacción `DB::transaction()` con `lockForUpdate()`.
  - **Re-chequeo de candado tardío:** Si el autor activó el candado de la etapa mientras la IA procesaba, la sobreescritura se aborta automáticamente respetando el candado.
  - **Fusión atómica:** Se leen los `user_locks` frescos de la base de datos (preservando `pinned_terms`, justificaciones y candados añadidos en paralelo) y solo se purgan los diffs manuales de la etapa concreta que se regenera.
  - Incremento atómico de `lock_version`.
- **`generateShotsBreakdown`:**
  - Persistencia de telemetría y transición de etapa bajo transacción con `lockForUpdate()`.
- **`recordManualOverride`:**
  - Delegado a `mutateUserLocks` para aislar el registro de diffs manuales.

### C. Livewire Studio (`ScriptEditor.php`)
- **Propiedad `$editLockVersion`:**
  - Capturada al iniciar la edición tanto en el grid principal (`startEditBlock`) como en el drawer (`startEditingInDrawer` / `openBlockDetails`).
- **`saveBlock` y `saveBlockFromDrawer`:**
  - Validación de versión optimista (`lock_version !== editLockVersion`). Si otro usuario o proceso modificó el bloque entretanto, se rechaza la sobreescritura y se notifica al autor para evitar la pérdida silenciosa de cambios o la generación de diffs manuales espurios.
- **`toggleStageLock` y `removePinnedTerm`:**
  - Ejecución atómica mediante `mutateUserLocks`.
- **`analyzePlotHoles` y `clearPlotHoles`:**
  - Persistencia atómica por bloque con re-lectura bajo `lockForUpdate()`, modificando estrictamente `plot_hole` y `plot_hole_stage` sin pisar la columna `user_locks` completa desde colecciones en memoria desactualizadas.
- **`savePlotHoleJustification`:**
  - Mutación atómica mediante `mutateUserLocks`.

## 3. Pruebas Automatizadas
- Creado `tests/Feature/UserLocksConcurrencyTest.php` con 7 escenarios de prueba:
  1. `test_transition_to_structured_preserves_concurrent_pinned_terms_added_during_ai_call`: Verifica preservación de términos fijados concurrentemente durante la llamada de IA.
  2. `test_transition_to_structured_aborts_text_overwrite_if_stage_was_locked_concurrently`: Verifica que un candado activado concurrentemente detiene la sobreescritura de texto.
  3. `test_save_block_rejects_update_when_lock_version_collides_optimistic_locking`: Verifica detección de colisión optimista en edición en cuadrícula.
  4. `test_save_block_from_drawer_rejects_update_when_lock_version_collides`: Verifica detección de colisión optimista en edición desde drawer.
  5. `test_toggle_stage_lock_mutates_atomically_under_pessimistic_lock`: Verifica mutación atómica de candados.
  6. `test_analyze_plot_holes_preserves_concurrent_user_locks_on_blocks`: Verifica preservación de locks durante la auditoría de agujeros.
  7. `test_save_plot_hole_justification_mutates_locks_atomically`: Verifica registro atómico de justificaciones.

## 4. Verificación
- Suite completa de `jg_filmmaker`: 121 tests pasados (619 aserciones).
- Linter Pint: 0 errores.
- Script global `./scripts/check.sh --cicd -p jg_filmmaker`: Exitoso en las 4 fases.
