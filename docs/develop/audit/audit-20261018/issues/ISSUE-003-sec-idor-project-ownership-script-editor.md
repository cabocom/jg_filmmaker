# [ISSUE-003] Ausencia total de autorización por propietario: `/projects/{project}` y `ScriptEditor` operan con IDs sin acotar (IDOR multitenant)

- **Severidad:** Crítica
- **Componente:** `jg_filmmaker`
- **Ubicación:** `routes/web.php:30`, `app/Livewire/Studio/ScriptEditor.php:191-193, 273, 329, 380, 425, 570, 601, 629, 656, 710, 735, 880, 894, 931, 971, 1011, 1038, 1063, 1090, 1402, 1408, 1457, 1491, 1529, 1540, 1551, 1779, 1802`
- **Categoría:** Seguridad

## 1. Descripción del Problema
El proyecto se asume protegido por usar UUID, pero **UUID no es autorización**. En todo el código no existe ninguna `Policy`, `Gate::authorize`, ni scope por usuario (`app/Policies` no existe; `grep authorize` → 0 resultados).

1. La ruta `GET /projects/{project}` sólo exige `auth`. `ScriptEditor::mount(Project $project)` no comprueba `$project->user_id === auth()->id()`: cualquier usuario autenticado que conozca/obtenga un UUID (logs, URLs compartidas, capturas, el propio historial del navegador, UUIDv7 con prefijo temporal — ver ISSUE-006) abre, lee y edita el proyecto ajeno. Además `mount()` **escribe** (sincroniza personajes/localizaciones).
2. Todas las acciones Livewire reciben `$blockId`, `$sceneId`, `$takeId`, `$shotId` como argumentos o propiedades públicas editables por el cliente y las resuelven con `ScriptBlock::findOrFail($id)`, `Scene::findOrFail`, `Take::findOrFail`, `Shot::findOrFail`, **sin verificar que pertenezcan a `$this->project`**. Incluso con acceso legítimo al propio proyecto, el atacante manipula IDs de otro proyecto.

## 2. Evidencia de Código
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/projects/{project}', ScriptEditor::class)->name('projects.show');   // sin ownership
});

public function mount(Project $project): void { $this->project = $project; ... }

public function toggleGoodTake(string $takeId): void
{
    $take = Take::findOrFail($takeId);          // cualquier take de cualquier tenant
    $take->update(['is_good_take' => ! $take->is_good_take]);
}
public function deleteTake(string $takeId): void { $take = Take::findOrFail($takeId); ... $take->delete(); }
```

## 3. Impacto Operativo o de Seguridad
Lectura, modificación y borrado (incluidos ficheros de vídeo) de guiones, tomas y Biblia de cualquier usuario; consumo de la cuota de IA ajena (`structureBlock` sobre bloques ajenos imputa telemetría al proyecto de la víctima o lo hace con contenido ajeno). Incumple la promesa documentada de "aislamiento multitenant anti-IDOR" (`docs/01_architecture.md` §3).

## 4. Solución Recomendada
1. Policy + autorización en el binding:
```php
// app/Policies/ProjectPolicy.php
public function view(User $u, Project $p): bool { return $u->is_admin ? false : $p->user_id === $u->id; }

// ScriptEditor::mount
$this->authorize('view', $project);
```
y `Route::get(...)->middleware('can:view,project')`.
2. Resolver siempre a través del proyecto:
```php
private function block(string $id): ScriptBlock
{
    return ScriptBlock::whereHas('scene', fn ($q) => $q->where('project_id', $this->project->id))->findOrFail($id);
}
private function take(string $id): Take
{
    return Take::whereHas('shot.scene', fn ($q) => $q->where('project_id', $this->project->id))->findOrFail($id);
}
```
3. Marcar `#[Locked]` las propiedades de identidad (`$project`, `activeSceneId`, `detailBlockId`, `editingBlockId`, `uploadingTakeId`, `deletingBlockId`, `deletingSceneId`, ...) y revalidar pertenencia en cada acción.
4. Tests de regresión: usuario B accede/edita recursos del proyecto de A → 403/404 en cada acción.
5. Documentar que el administrador **no** puede abrir el editor de proyectos ajenos (privacidad estricta documentada en `docs/08`).
