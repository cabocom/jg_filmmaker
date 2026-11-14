# Análisis Técnico - ISSUE-040: Retención Prunable, Deduplicación de Snapshots y Purga en Cascada de Historial de Actividad

## 1. Contexto y Diagnóstico
La tabla `user_activity_logs` acumulaba registros de actividad con blobs JSON de snapshots completos (`raw_text`, `structured_text`, `screenplay_content`):
1. **Falta de retención:** No existía una política de purga automática periódica (`Prunable`), provocando un crecimiento continuo en SQLite.
2. **Duplicación de snapshots idénticos:** Cada acción duplicaba el texto íntegro incluso cuando el contenido no variaba.
3. **Persistencia tras borrado de proyecto:** Al borrar un proyecto, los registros de actividad permanecían en la base de datos sin purgarse.
4. **Atribución inexacta:** `logActivity()` utilizaba `$this->project->user` cuando no había usuario autenticado en sesión.

## 2. Solución Diseñada
1. **Trait `Prunable` en `UserActivityLog`:**
   - Política de retención de 90 días mediante `created_at <= now()->subDays(90)`.
2. **Purga en cascada al eliminar proyecto:**
   - Hook `static::deleting` en `Project.php` que elimina todos los `activityLogs` vinculados antes de confirmar el borrado.
3. **Deduplicación de snapshots:**
   - Comparación con el último snapshot idéntico de la misma entidad y acción, evitando guardar blobs redundantes.
4. **Atribución estricta:**
   - `auth()->id()` obligatorio para registrar eventos.
5. **Carga acotada en UI:**
   - Límite a 50 registros más recientes en el modal de actividad.

## 3. Estrategia de Pruebas
- `tests/Feature/ActivityLogsPrunableAndCleanupTest.php`:
  - `test_user_activity_log_is_prunable_after_90_days`.
  - `test_deleting_project_purges_associated_activity_logs_cascade`.
