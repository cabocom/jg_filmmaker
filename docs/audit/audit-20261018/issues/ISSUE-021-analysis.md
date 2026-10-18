# ISSUE-021 Análisis: Autorización de Acciones de Administración y Trazabilidad Forense

## 1. Diagnóstico del Problema
- Las acciones críticas en los componentes Livewire del panel de administración (`UserList::toggleAdmin`, `SecurityLogsViewer::clearLogs`, `ProjectManager::transferProject`, `PricingManager::setActiveRate/saveRate/createRate/deleteRate`) se ejecutaban sin validar la autorización de Gate en el cuerpo de los métodos.
- Si un snapshot de Livewire se enviaba con sesión modificada o degradada, o vía manipulaciones de componentes, no se reaplicaba el middleware de admin porque no estaba registrado como middleware persistente de Livewire.
- Se permitía que un administrador se auto-revocara el rol a sí mismo o dejara la aplicación sin administradores.
- `SecurityLogsViewer::clearLogs()` ejecutaba `truncate()` sin dejar ningún registro de auditoría.
- `PricingManager::deleteRate()` permitía eliminar la tarifa activa de facturación.
- `ProjectManager::transferProject()` transfería obras de cualquier usuario sin registrar quién, cuándo ni por qué.

## 2. Estrategia de Mitigación
1. Registro de Gate `admin` en `AppServiceProvider` y `$this->authorize('admin')` al inicio de cada acción administrativa.
2. Inclusión de `EnsureUserIsAdmin` en los middlewares persistentes de Livewire (`Livewire::addPersistentMiddleware`).
3. Creación del modelo y tabla `admin_audit_logs` para registrar todas las operaciones sensibles con actor, acción, objetivo, detalles en JSON e IP.
4. Salvaguardas en `UserList`: impedir revocar el rol al propio administrador logueado y garantizar un mínimo de 1 administrador activo en el sistema.
5. Salvaguarda en `PricingManager`: bloquear la eliminación de tarifas activas.
6. En `SecurityLogsViewer`: registrar la evidencia forense en `admin_audit_logs` con la cantidad de registros antes de purgar.
