# Análisis Técnico: [ISSUE-003] Ausencia total de autorización por propietario: `/projects/{project}` y `ScriptEditor` operan con IDs sin acotar (IDOR multitenant)

- **Componente:** `jg_filmmaker`
- **Severidad:** Crítica
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidad:** CWE-639 (Authorization Bypass Through User-Controlled Key / IDOR) / CWE-285 (Improper Authorization)

---

## 1. Diagnóstico del Estado Previo

1. **Ausencia de Políticas de Autorización (`Policies`)**:
   - No existía el directorio `app/Policies` ni clases `ProjectPolicy`.
   - La ruta `GET /projects/{project}` sólo aplicaba el middleware genérico `auth`, sin verificar la titularidad del proyecto (`user_id === auth()->id()`).
   - El componente `ScriptEditor::mount(Project $project)` asignaba `$this->project = $project` sin autorización. Cualquier usuario autenticado que conociera o enumerara un UUID de proyecto ajeno podía cargar el editor completo, leer el guion literario, la Biblia y las tomas.
   - En `mount()`, además, se invocaba la sincronización automática de personajes y localizaciones (`$pipeline->syncProjectCharacters(...)`), realizando escrituras no autorizadas sobre el proyecto de la víctima simplemente al abrir la URL.

2. **Resolución de Modelos sin Alcance (Unscoped FindOrFail)**:
   - En más de 25 métodos de `ScriptEditor.php`, las entidades hijas (`ScriptBlock`, `Scene`, `Shot`, `Take`) se resolvían mediante `ScriptBlock::findOrFail($id)`, `Scene::findOrFail($id)`, `Take::findOrFail($id)`.
   - Si un atacante manipulaba las peticiones Livewire con IDs de bloques o tomas de otro usuario, el servidor las ejecutaba sin comprobar si pertenecían a `$this->project`.

3. **Privacidad Estricta del Creador frente a Administradores**:
   - Tal como se define en la arquitectura (`docs/01_architecture.md`) y la especificación de privacidad (`docs/08`), las obras cinematográficas y guiones son confidenciales: **ningún usuario ajeno, ni siquiera con rol de administrador (`is_admin = true`), debe tener acceso de lectura o edición al estudio de otro cineasta**.

---

## 2. Alternativas Evaluadas

1. **Comprobar `$project->user_id === Auth::id()` manualmente en cada controlador/Livewire:**
   - *Descartada:* Fragil y propensa a olvidos en futuras ampliaciones.
2. **Implementación de `ProjectPolicy` + Middleware de Binding + Métodos Scoped de Proyecto (Opción Seleccionada):**
   - *Ventajas:*
     - Política canónica `ProjectPolicy` registrada en el ciclo de vida de Laravel.
     - Middleware de ruta `can:view,project` en `routes/web.php` para bloquear el acceso antes de instanciar componentes.
     - `Gate::authorize('view', $project)` en `ScriptEditor::mount()`.
     - Atributo `#[Locked]` en la propiedad pública `$project`.
     - Métodos auxiliares protegidos (`projectBlock`, `projectScene`, `projectShot`, `projectTake`) que garantizan que toda consulta Eloquent resuelva exclusivamente dentro del ámbito `project_id == $this->project->id`.

---

## 3. Plan de Implementación

1. **Crear `App\Policies\ProjectPolicy` (`app/Policies/ProjectPolicy.php`):**
   - Métodos `view(User $user, Project $project): bool`, `update(...)`, `delete(...)`.
   - Retornar `$project->user_id === $user->id` (estricta privacidad, sin bypass de admin para el editor).

2. **Asegurar la ruta en `routes/web.php`:**
   - Añadir middleware `can:view,project` a `Route::get('/projects/{project}', ScriptEditor::class)`.

3. **Asegurar `ScriptEditor` (`app/Livewire/Studio/ScriptEditor.php`):**
   - Marcar `#[Locked] public Project $project;`.
   - En `mount(Project $project)`: invocar `\Illuminate\Support\Facades\Gate::authorize('view', $project);` antes de cualquier sincronización o lectura.
   - Definir métodos de resolución segura:
     - `projectBlock(string $id, array $with = []): ScriptBlock`
     - `projectScene(string $id): Scene`
     - `projectShot(string $id): Shot`
     - `projectTake(string $id, array $with = []): Take`
   - Reemplazar todas las llamadas directas `ScriptBlock::findOrFail`, `Scene::findOrFail`, `Shot::findOrFail`, `Take::findOrFail` por estos métodos de ámbito seguro.

4. **Pruebas Automatizadas de Aislamiento Multitenant (`tests/Feature/MultiTenantProjectIsolationTest.php`):**
   - Verificar que el Usuario A no puede acceder a `/projects/{project_B}` (HTTP 403 Forbidden).
   - Verificar que un Administrador tampoco puede acceder al editor de un proyecto ajeno (HTTP 403 Forbidden).
   - Verificar que invocar acciones de `ScriptEditor` con IDs de bloques o tomas ajenas resulta en 404 / ModelNotFoundException sin alterar la base de datos.
