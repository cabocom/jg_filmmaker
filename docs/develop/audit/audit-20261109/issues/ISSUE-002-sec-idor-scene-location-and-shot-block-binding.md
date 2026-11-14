# [ISSUE-002] IDOR Cross-Tenant en Asociación de Localizaciones a Escenas y Bloques a Planos

- **Severidad:** Alta
- **Estado:** Resuelto (Cerrado)
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Studio/Concerns/WithEditorSceneOperations.php:91-185`, `jg_filmmaker/app/Livewire/Studio/Concerns/WithEditorTakes.php:25-45`, `jg_filmmaker/app/Livewire/Studio/Concerns/WithEditorShots.php:153-159`, `jg_filmmaker/app/Actions/Studio/ManageScriptStructureAction.php:118-152`
- **Categoría:** Seguridad

## 1. Descripción
En el componente `ScriptEditor`, los métodos `createScene()` y `updateScene()` persisten directamente `$this->newSceneLocationId` y `$this->editingSceneLocationId` en la columna `scenes.location_id` sin validar que el registro `Location` pertenezca a `$this->project->id`. La validación únicamente comprueba `'nullable|string'`.

De forma análoga, el método `addShot()` asigna `$this->newShotBlockId` directamente al campo `shots.script_block_id` sin verificar que el `ScriptBlock` pertenezca a la escena activa ni al proyecto del usuario autenticado.

Dado que el motor del pipeline (`ScriptPipelineService::transitionToStructured` y `transitionToScreenplay`) carga la relación `$block->scene->location` e inyecta su nombre, tipo (`INT`/`EXT`) y notas de dirección de arte en el contexto enviado al LLM y en la generación del `slugline`, vincular un `location_id` ajeno provoca la filtración indirecta de metadatos de localización de otro proyecto.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L808-L834):
```php
    public function createScene(): void
    {
        $this->authorize('update', $this->project);

        $this->validate([
            'newSceneSlugline' => 'required|string|max:255',
            'newSceneTimeOfDay' => 'required|string|max:50',
            'newSceneLocationId' => 'nullable|string',
        ]);

        $maxOrder = $this->project->scenes()->max('scene_order') ?? 0;

        $scene = $this->project->scenes()->create([
            'scene_order' => $maxOrder + 1,
            'slugline' => strtoupper($this->newSceneSlugline),
            'location_id' => $this->newSceneLocationId ?: null,
            'time_of_day' => strtoupper($this->newSceneTimeOfDay),
        ]);
```

Y en [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L2344-L2366):
```php
    public function addShot(): void
    {
        $this->authorize('update', $this->project);
        // ...
        $shot = Shot::create([
            'scene_id' => $this->activeSceneId,
            'script_block_id' => $this->newShotBlockId ?: null,
            'shot_number' => $maxNumber + 1,
            'shot_type' => $this->newShotType,
            'description' => trim($this->newShotDescription),
        ]);
```

## 3. Impacto Operativo o Riesgo
- **Violación de Aislamiento Multitenant en Claves Foráneas:** Permite referenciar entidades `Location` o `ScriptBlock` pertenecientes a proyectos de otros usuarios dentro de escenas o planos propios.
- **Exfiltración en Pipeline:** Al procesar un bloque en una escena vinculada a una `Location` ajena, `ScriptPipelineService` lee `$scene->location->name` y `$scene->location->art_department_notes` y los vuelca en el tratamiento/guion resultante.

## 4. Solución o Mejora Recomendada
Validar explícitamente con `Rule::exists` acotado por `project_id` (para `Location`) y por `scene_id` (para `ScriptBlock`) antes de persistir:

```php
use Illuminate\Validation\Rule;

// En createScene() y updateScene():
$this->validate([
    'newSceneSlugline' => ['required', 'string', 'max:255'],
    'newSceneTimeOfDay' => ['required', 'string', 'max:50'],
    'newSceneLocationId' => [
        'nullable',
        'uuid',
        Rule::exists('locations', 'id')->where('project_id', $this->project->id),
    ],
]);

// En addShot():
$this->validate([
    'newShotType' => ['required', 'string', 'max:30'],
    'newShotDescription' => ['required', 'string', 'max:2000'],
    'newShotBlockId' => [
        'nullable',
        'uuid',
        Rule::exists('script_blocks', 'id')->where('scene_id', $this->activeSceneId),
    ],
]);
```

## 5. Estado y Resolución Técnica (Noviembre 2026)

- **Validación en Capa UI / Livewire:**
  - En `WithEditorSceneOperations.php`, los métodos `createScene()` y `updateScene()` incorporan validación estricta de `$newSceneLocationId` y `$editingSceneLocationId` mediante `Rule::exists('locations', 'id')->where('project_id', $this->project->id)`, con normalización de cadenas vacías a `null`.
  - En `WithEditorTakes.php`, el método `addShot()` valida `$newShotBlockId` acotado a la escena activa con `Rule::exists('script_blocks', 'id')->where('scene_id', $scene->id)`, asegurando que no se puedan vincular bloques de otros proyectos ni de escenas ajenas.
  - En `WithEditorShots.php`, el método `startNewShotForBlock()` valida mediante `findOrFail` que el bloque suministrado pertenezca al proyecto autenticado antes de abrir el modal.
- **Defensa en Profundidad en Capa de Dominio / Acciones:**
  - En `ManageScriptStructureAction.php`, `createScene()` y `updateScene()` consultan `$project->locations()->where('id', $data['location_id'])->value('id')`, forzando `null` si el ID de localización no pertenece al proyecto.
- **Suite de Pruebas Automatizadas:**
  - Creada suite de pruebas dedicada en `tests/Feature/SceneAndShotIdorProtectionTest.php` (6 tests completos validando rechazos IDOR y persistencia correcta para localizaciones y bloques).
  - Suite de 247 tests pasando al 100% (1.208 aserciones) y Pint con 0 infracciones.
- **GitLab Issue:** [jg_filmmaker#7](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/7) cerrado.


