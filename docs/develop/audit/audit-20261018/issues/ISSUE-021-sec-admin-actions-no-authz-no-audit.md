# [ISSUE-021] Panel de administración: sin autorización en las acciones, sin salvaguardas ni auditoría (`toggleAdmin`, `clearLogs`, transferencias)

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Admin/UserList.php:14-20`, `app/Livewire/Admin/SecurityLogsViewer.php:17-22`, `app/Livewire/Admin/ProjectManager.php:44-70`, `app/Livewire/Admin/PricingManager.php`, `app/Http/Middleware/EnsureUserIsAdmin.php:16-20`, `config/livewire.php:135`
- **Categoría:** Seguridad

## 1. Descripción del Problema
- La protección de admin es sólo el middleware `EnsureUserIsAdmin` en el grupo de rutas. Los componentes **no autorizan en sus métodos** y no se registra `Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class])`: Livewire reaplica en `/livewire/update` únicamente el middleware persistente por defecto. Un administrador degradado (o con sesión robada ya caducada su rol) que conserve un snapshot abierto puede seguir invocando `toggleAdmin`, `transferProject`, `deleteRate`, `clearLogs`.
- `toggleAdmin($userId)`: sin límite (un admin puede quitarse el rol a sí mismo o dejar la plataforma sin administradores), sin confirmación ni **registro de auditoría**; no se pide reautenticación.
- `SecurityLogsViewer::clearLogs()` ejecuta `SecurityBreachLog::truncate()`: un administrador (o un atacante que haya llegado a admin, ver ISSUE-002) **borra la evidencia forense** de replay attacks sin dejar rastro.
- `ProjectManager::transferProject` transfiere proyectos de cualquier usuario sin registrar quién, cuándo ni por qué (el `UserActivityLog` es del usuario propietario); contradice la "privacidad estricta" al otorgar la propiedad total del guion a otra cuenta sin consentimiento del autor.
- `PricingManager`: cambiar tarifas (`setActiveRate`, `deleteRate`) altera retroactivamente los costes estimados mostrados (se calculan por tarifa activa en el momento del registro, pero no se versionan); `deleteRate` permite eliminar la tarifa activa dejando el valor por defecto fijo en código.
- `EnsureUserIsAdmin` → `abort(403)` también para no autenticados (debería redirigir a login).

## 2. Evidencia de Código
```php
public function toggleAdmin(string $userId): void
{
    $user = User::findOrFail($userId);
    $user->is_admin = ! $user->is_admin;
    $user->save();                      // sin guardia, sin auditoría
}
public function clearLogs(): void { SecurityBreachLog::truncate(); }
```

## 3. Impacto Operativo o de Seguridad
Escalada y persistencia de privilegios, lockout administrativo, destrucción de evidencia forense y cambios de titularidad sin trazabilidad.

## 4. Solución Recomendada
- `Gate::define('admin')` + `$this->authorize('admin')` al inicio de **cada** acción, y `Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class])`.
- Tabla `admin_audit_logs` (actor, acción, objetivo, IP, timestamp) para toggle, transfer, tarifas y purgas; prohibir `truncate` (usar retención con `prune`).
- Impedir `toggleAdmin` sobre uno mismo y garantizar ≥ 1 admin; reautenticación (OTP) para acciones sensibles.
- Versionar tarifas (`effective_from`) y bloquear el borrado de la activa.
