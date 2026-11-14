# [ISSUE-006] Ausencia de Gate Explícito y de Registro en `AdminAuditLog` en `UserDetail::transferProject`

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Admin/UserDetail.php:31-84`
- **Categoría:** Seguridad

## 1. Descripción
En el panel de administración existe una discrepancia de seguridad y auditoría forense entre los dos componentes que permiten transferir la propiedad de un proyecto entre usuarios:
- En [`ProjectManager::transferProject()`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/ProjectManager.php#L31-L82), se invoca explícitamente `$this->authorize('admin')` en cada acción pública de mutación y se registra la operación en `AdminAuditLog` con `action => 'transfer_project'`, `previous_user_id` y `new_user_id`.
- En cambio, en [`UserDetail::openTransferModal()`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/UserDetail.php#L31-L47) y [`UserDetail::transferProject()`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/UserDetail.php#L49-L84), **no se invoca `$this->authorize('admin')`** dentro de los métodos de mutación Livewire (dependiendo únicamente del middleware de carga inicial o de rehidratación de ruta) y **se omite por completo el registro en `AdminAuditLog`**.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Admin/UserDetail.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/UserDetail.php#L49-L84):
```php
    public function transferProject(): void
    {
        $this->validate([
            'targetUserId' => 'required|exists:users,id',
        ]);

        if (! $this->transferringProjectId) {
            return;
        }

        $project = Project::find($this->transferringProjectId);
        // ...
        $project->update([
            'user_id' => $newOwner->id,
        ]);

        session()->flash('status', "Proyecto '{$project->title}' transferido a {$newOwner->name} ({$newOwner->email}).");
        $this->cancelTransfer();
    }
```

## 3. Impacto Operativo o Riesgo
- **Pérdida de Trazabilidad Forense:** Las transferencias de propiedad de proyectos ejecutadas desde la vista de detalle de usuario (`/admin/users/{user}`) no dejan ningún rastro en `admin_audit_logs`.
- **Defensa en Profundidad Incompleta:** Si un administrador pierde su privilegio `is_admin` con el componente ya montado en el navegador, la acción Livewire `transferProject` carece de revalidación explícita del Gate `admin`.

## 4. Solución o Mejora Recomendada
Añadir `$this->authorize('admin');` al inicio de `openTransferModal()` y `transferProject()`, y registrar el evento en `AdminAuditLog::create(...)` de forma idéntica a `ProjectManager`:

```php
    public function transferProject(): void
    {
        $this->authorize('admin');

        $this->validate([
            'targetUserId' => 'required|exists:users,id',
        ]);
        // ...
        $previousOwnerId = $project->user_id;
        $project->update(['user_id' => $newOwner->id]);

        AdminAuditLog::create([
            'admin_user_id' => auth()->id(),
            'target_user_id' => $newOwner->id,
            'action' => 'transfer_project',
            'metadata' => [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'previous_user_id' => $previousOwnerId,
                'new_user_id' => $newOwner->id,
            ],
            'ip_address' => request()->ip(),
        ]);
```

## 5. Resolución y Verificación (2026-11-14)

### Implementación Realizada
- **Defensa en Profundidad con Gate `admin` en `UserDetail`:**
  - Se añadió `$this->authorize('admin');` al inicio de `openTransferModal(string $projectId)` y de `transferProject()`, blindando las acciones públicas Livewire contra llamadas no autorizadas ante degradación de sesión.
- **Trazabilidad Forense en `AdminAuditLog`:**
  - Se agregó la creación del registro en `AdminAuditLog` dentro de la transacción `DB::transaction()` en `UserDetail::transferProject()`, capturando:
    - `user_id`: ID del administrador que realiza la transferencia.
    - `action`: `'transfer_project'`.
    - `target_type`: `Project::class`.
    - `target_id`: ID del proyecto transferido.
    - `details`: `project_title`, `previous_owner_id`, `new_owner_id`, `new_owner_email`.
    - `ip_address`: Dirección IP del cliente (`request()->ip()`).
  - La auditoría queda 100% simétrica con `ProjectManager::transferProject()`.

### Verificación y Cobertura
- **Pruebas Automatizadas:** `tests/Feature/ProjectTransferAdminTest.php` ampliado con:
  - Verificación `assertDatabaseHas('admin_audit_logs', ...)` tras la transferencia exitosa desde `UserDetail`.
  - `test_non_admin_cannot_open_modal_or_transfer_project_in_user_detail`: Comprueba que usuarios sin rol administrador (`is_admin = false`) reciben un `403 Forbidden` al invocar `openTransferModal` o `transferProject` en Livewire, y que la titularidad del proyecto se mantiene intacta.
- **Validaciones CI/CD:**
  - Pint: 0 infracciones.
  - PHPUnit: 275 tests pasados al 100% (1.302 assertions).


