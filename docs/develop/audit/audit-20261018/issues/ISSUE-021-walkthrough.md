# ISSUE-021 Walkthrough: Autorización en Métodos y Auditoría de Acciones de Administración

## Resumen de Cambios

1. **Gate y Middleware Persistente:**
   - En `AppServiceProvider::boot()`, se definió `Gate::define('admin', fn (User $user) => (bool) $user->is_admin);`.
   - Se añadió `Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class]);` para que Livewire valide siempre los privilegios de administrador en `/livewire/update`.
   - En `EnsureUserIsAdmin::handle()`, las solicitudes no autenticadas se redirigen a `route('login')` (o 401 si se espera JSON) y sólo se aborta con 403 a usuarios autenticados sin privilegios.

2. **Modelo y Tabla `admin_audit_logs`:**
   - Migración `2026_10_18_220000_create_admin_audit_logs_table.php` y modelo `App\Models\AdminAuditLog`.
   - Almacena: `user_id`, `action`, `target_type`, `target_id`, `details` (JSON) e `ip_address`.

3. **Salvaguardas y Auditoría en Componentes:**
   - `UserList::toggleAdmin()`: `$this->authorize('admin')`, bloqueo de auto-revocación, bloqueo de revocación del único admin existente, y registro en `admin_audit_logs`.
   - `ProjectManager::transferProject()`: `$this->authorize('admin')`, transacción atómica y registro de titular anterior/nuevo en `admin_audit_logs`.
   - `PricingManager`: `$this->authorize('admin')` en todas sus acciones, bloqueo de eliminación de la tarifa activa (`deleteRate`) y auditoría en `admin_audit_logs`.
   - `SecurityLogsViewer::clearLogs()`: `$this->authorize('admin')`, auditoría de la cantidad de eventos antes de purgar, y eliminación segura.
   - Vistas Blade actualizadas con alertas de error de sesión `@if(session()->has('error'))`.

4. **Verificación Automatizada:**
   - `tests/Feature/AdminSecurityAndDemoIsolationTest.php` valida que usuarios no administradores no pueden acceder a rutas de admin ni invocar métodos Livewire, que los administradores no pueden auto-revocarse ni borrar la tarifa activa, y que todas las acciones generan registros en `admin_audit_logs`.
