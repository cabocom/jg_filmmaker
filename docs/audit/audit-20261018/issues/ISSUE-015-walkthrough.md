# Walkthrough - ISSUE-015: Preservación de Candados, Diffs Manuales y Protección de Planos contra Sobreescritura Destructiva

## Resumen de la Implementación

En `ISSUE-015`, se resolvió la pérdida destructiva de modificaciones manuales de usuario (`manual_diffs`) durante regeneraciones del pipeline y la desprotección de planos técnicos (`shots`) frente a regeneraciones accidentales de IA:

1. **Archivado no destructivo de diffs manuales:**
   - En `ScriptPipelineService::transitionToStructured()` y `ScriptPipelineService::transitionToScreenplay()`, cuando una etapa se regenera, sus diffs manuales asociados ya no se eliminan silenciosamente.
   - Ahora se archivan en `user_locks['archived_diffs']` estampando una marca de tiempo ISO-8601 (`archived_at`), manteniendo la trazabilidad histórica del autor y permitiendo la recuperación o auditoría de cambios manuales previos.

2. **Gobernanza y candados en la etapa de Planos Técnicos (`shots`):**
   - Se añadió validación de candado `isStageLocked('shots')` en `ScriptPipelineService::generateShotsBreakdown()` devolviendo la colección existente sin realizar llamadas a IA ni alterar el estado.
   - En `ScriptEditor::breakdownBlockShots()`, se valida `isStageLocked('shots')` antes de invocar al pipeline, emitiendo una notificación de advertencia al autor sin modificar registros.
   - En `ScriptEditor::toggleStageLock()`, se incluyó la etapa `'shots'` en la lista blanca permitida (`['structured', 'screenplay', 'shots']`), con feedback explícito de candado.

3. **Protección de planos técnicos con tomas grabadas en rodaje:**
   - En `ScriptPipelineService::generateShotsBreakdown()`, antes de recrear o alterar un plano técnico (`Shot`), se verifica si ya posee tomas registradas en claqueta (`takes()->count() > 0`). Si tiene tomas asociadas, el plano y sus tomas se preservan íntegramente, previniendo la corrupción de material de rodaje ya filmado.

4. **Sustitución posicional anclada de diffs en `AgentClientService`:**
   - En `AgentClientService::applyUserLocks()`, se reemplazó la invocación voraz global de `str_replace` por `substr_replace`, anclando la sustitución únicamente a la primera ocurrencia encontrada de la discrepancia.

5. **Auditoría y trazabilidad en el editor (`ScriptEditor`):**
   - Al invocar `structureBlock()` y `formatBlockToScreenplay()`, se capturan y registran en `logActivity()` los estados previos del texto (`previous_structured_text` y `previous_screenplay_content`), garantizando la auditoría completa de regeneraciones.

## Archivos Modificados y Creados

- `app/Services/ScriptPipelineService.php`: Archivado de diffs en `user_locks['archived_diffs']`, respeto a `isStageLocked('shots')` y preservación de planos con tomas.
- `app/Livewire/Studio/ScriptEditor.php`: Whitelist de candados ampliada a `shots`, bloqueo en `breakdownBlockShots()`, y captura de estado previo en `logActivity()`.
- `app/Services/JgAgente/AgentClientService.php`: Sustitución segura mediante `substr_replace` anclada a la primera coincidencia y compatibilidad de claves de diff.
- `tests/Feature/UserLocksPreservationTest.php`: Suite de 5 tests exhaustivos cubriendo archivado de diffs, candado de planos, bloqueo en Livewire, protección de planos con tomas y sustitución singular de diffs.

## Verificación de Calidad

- **Linters:** `./vendor/bin/pint --test` pasado con 0 errores de formato.
- **Tests Automatizados:** `php artisan test --filter=UserLocksPreservationTest` (5 tests, 15 aserciones, 100% pasando).
- **Suite Completa:** `php artisan test` (129 tests, 650 aserciones, 100% pasando).
