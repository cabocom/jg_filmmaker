# Walkthrough - ISSUE-040: Retención Prunable, Deduplicación de Snapshots y Purga en Cascada de Historial de Actividad

## 1. Modificaciones Implementadas
- **`app/Models/UserActivityLog.php`**: Implementación de `Prunable` con ventana de retención de 90 días.
- **`app/Models/Project.php`**: Adición de `static::deleting` para purga en cascada de `activityLogs`.
- **`app/Livewire/Studio/ScriptEditor.php`**:
  - Deduplicación de snapshots idénticos en `logActivity()`.
  - Atribución estricta al usuario autenticado (`auth()->id()`).
  - Acotamiento de la consulta de logs en `render()` a los 50 registros más recientes.

## 2. Verificación de Calidad
- Pint: 0 errores.
- Tests: `tests/Feature/ActivityLogsPrunableAndCleanupTest.php` pasa al 100%.
- Suite completa: 193 tests pasando en `jg_filmmaker`.
