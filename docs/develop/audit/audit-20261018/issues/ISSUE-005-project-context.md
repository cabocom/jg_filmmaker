# Project Context: [ISSUE-005] Reordenación de bloques con aislamiento multitenant estricto e integridad referencial

## Resumen del Cambio
Se corrigió la vulnerabilidad de acceso trans-proyecto en `ScriptEditor::reorderBlock()` que permitía a un usuario mover o transferir bloques de guión de proyectos ajenos hacia escenas propias mediante la manipulación del parámetro `$draggedBlockId`. Además, se implementó la reasignación en cascada de `scene_id` en los planos técnicos (`shots`) dependientes y la reindexación secuencial del orden de bloques (`block_order`) tanto en la escena de origen como en la de destino dentro de una transacción atómica.

## Archivos Modificados / Creados
- `app/Livewire/Studio/ScriptEditor.php`: Validación estricta con `$this->projectScene()` y `$this->projectBlock()`, actualización de planos dependientes y reordenación de ambas escenas.
- `tests/Feature/BlockReorderCrossTenantIsolationTest.php`: Suite de tests validando bloqueo de robo trans-proyecto y consistencia de datos.
- `docs/audit/audit-20261018/issues/ISSUE-005-analysis.md`: Documento de análisis inicial y opciones técnicas.
- `docs/audit/audit-20261018/issues/ISSUE-005-walkthrough.md`: Documento de guía y detalles de implementación.
- `docs/audit/audit-20261018/issues/ISSUE-005-project-context.md`: Este documento de contexto.

## Impacto en Seguridad y Arquitectura
- **Aislamiento Multitenant:** Erradicación total de cualquier vector de robo de bloques entre diferentes proyectos cinematográficos en el editor de guion.
- **Integridad Referencial:** Coherencia de relación garantizada entre `scenes`, `script_blocks` y `shots` al mover bloques entre escenas de un mismo proyecto.
