# [ISSUE-001] IDOR Cross-Tenant en Acondicionamiento Multimodal de Storyboards (Personajes y Localizaciones)

- **Severidad:** Crítica
- **Estado:** Resuelto (Cerrado)
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/Storyboard/StoryboardPromptBuilder.php:84-142`, `jg_filmmaker/app/Livewire/Studio/Concerns/WithEditorStoryboard.php:75-215`, `jg_filmmaker/resources/views/livewire/studio/partials/editor/storyboard-generate-modal.blade.php:5-6`
- **Categoría:** Seguridad

## 1. Descripción
El servicio `StoryboardService` resuelve las entidades `Character` y `Location` seleccionadas en el modal de composición de storyboards ejecutando `Character::find($characterId)` y `Location::find($locationId)` de forma global, sin acotar la consulta al `project_id` de la escena o del plano (`$shot->scene->project_id`).

Adicionalmente, en el componente Livewire `ScriptEditor`, las propiedades públicas `$storyboardCharacterId` y `$storyboardLocationId` pueden ser modificadas desde el payload del cliente en cualquier petición de actualización (`updatedStoryboardCharacterId`, `updatedStoryboardLocationId`, `resetStoryboardPrompt`, `confirmGenerateStoryboard`) sin validar que dichos UUIDs pertenezcan al proyecto activo del usuario autenticado.

Dado que `buildPrompt()` inyecta directamente en `$this->storyboardModalPrompt` (visible en el textarea editable del cliente) el nombre, notas de vestuario (`costume_notes`), psicología (`psychology`) o notas de dirección de arte (`art_department_notes`), iluminación (`lighting_notes`) y dirección física (`address_reference`), y que `generateStoryboard()` lee la imagen de referencia del disco y la envía como `inline_data` multimodal al LLM, un usuario autenticado podría referenciar el UUID de un personaje o localización de otro tenant.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/StoryboardService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/StoryboardService.php#L226-L284):
```php
    public function resolveCharacterModel(Shot $shot, ?string $characterId = null): ?Character
    {
        if ($characterId) {
            return Character::find($characterId);
        }
        // ...
    }

    public function resolveLocationModel(Shot $shot, ?string $locationId = null): ?Location
    {
        if ($locationId) {
            return Location::find($locationId);
        }
```

Y en [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L1764-L1781):
```php
    public function updatedStoryboardCharacterId(): void
    {
        $this->refreshStoryboardModalPrompt();
    }

    public function updatedStoryboardLocationId(): void
    {
        $this->refreshStoryboardModalPrompt();
    }

    protected function refreshStoryboardModalPrompt(): void
    {
        if (! $this->storyboardModalShotId) {
            return;
        }
        $shot = Shot::with(['scene.project.characters', 'scene.location', 'scene.project.locations'])->find($this->storyboardModalShotId);
        // ...
        $this->storyboardModalPrompt = app(StoryboardService::class)->buildPrompt(
            $shot,
            $this->storyboardStyle,
            $this->storyboardCharacterId ?: null,
            $this->storyboardLocationId ?: null,
            count($this->storyboardExistingPropPaths) + count($this->storyboardPropUploads)
        );
    }
```

## 3. Impacto Operativo o Riesgo
- **Exfiltración de Propiedad Intelectual Cross-Tenant (Confidencialidad Alta):** Si un atacante conoce o intercepta un UUIDv4 de `Character` o `Location` perteneciente a otro proyecto/usuario, al asignarlo a `$storyboardCharacterId` o `$storyboardLocationId` obtiene inmediatamente en el campo `$storyboardModalPrompt` del navegador los textos privados de la Biblia de Producción de la víctima (`costume_notes`, `psychology`, `art_department_notes`, `lighting_notes`).
- **Uso No Autorizado de Activos Visuales:** Al ejecutar `confirmGenerateStoryboard()`, el backend lee la fotografía de referencia privada del personaje o localización del otro tenant y la utiliza para condicionar la imagen generada para el atacante.

## 4. Solución o Mejora Recomendada
Acotar estrictamente las consultas de resolución en `StoryboardService` al `project_id` de la escena asociada al plano (`$shot->scene->project_id`) y añadir reglas de validación `Rule::exists` con filtro `where('project_id', $this->project->id)` en `ScriptEditor`:

```php
    public function resolveCharacterModel(Shot $shot, ?string $characterId = null): ?Character
    {
        $projectId = $shot->scene?->project_id;
        if (! $projectId) {
            return null;
        }

        if ($characterId) {
            return Character::where('project_id', $projectId)->find($characterId);
        }
        // ...
    }

    public function resolveLocationModel(Shot $shot, ?string $locationId = null): ?Location
    {
        $projectId = $shot->scene?->project_id;
        if (! $projectId) {
            return null;
        }

        if ($locationId) {
            return Location::where('project_id', $projectId)->find($locationId);
        }
        // ...
    }
```

## 5. Estado y Resolución Técnica (Noviembre 2026)

- **Defensa en Profundidad por Capas:**
  1. **Capa de Dominio / Servicio (`StoryboardPromptBuilder`):** Se modificaron los métodos `resolveCharacterModel` y `resolveLocationModel` para acotar estrictamente cualquier consulta a la base de datos al `$projectId` del plano/escena (`Character::where('project_id', $projectId)->find($characterId)` y `Location::where('project_id', $projectId)->find($locationId)`). En caso de no existir o pertenecer a otro tenant/proyecto, devuelven `null` impidiendo que sus notas privadas se inyecten en el prompt o que sus imágenes turn-around se envíen a Google AI Studio.
  2. **Capa UI / Livewire (`WithEditorStoryboard`):** Se implementó `validateStoryboardReferences()` utilizando `Rule::exists` con cláusula estricta `where('project_id', $this->project->id)` para `$storyboardCharacterId` y `$storyboardLocationId`. Esta validación se ejecuta preventivamente en los hooks de actualización (`updatedStoryboardCharacterId`, `updatedStoryboardLocationId`), al resetear el prompt modal (`resetStoryboardModalPrompt`) y previo a la generación de la viñeta (`confirmGenerateStoryboard`).
  3. **Capa de Presentación Blade (`storyboard-generate-modal.blade.php`):** Se sustituyeron las consultas globales `Character::find()` y `Location::find()` por colecciones scoped al proyecto activo (`$this->project->characters->firstWhere('id', ...)` y `$this->project->locations->firstWhere('id', ...)`).
- **Cobertura de Pruebas Automatizadas:**
  - Se añadieron tests dedicados de prevención IDOR cross-tenant en `tests/Feature/StoryboardGenerationTest.php`:
    - `test_storyboard_service_prevents_idor_cross_tenant_character_and_location_leakage` (verificación de rechazo de IDs ajenos, no filtrado en prompts y no envío multimodal de imágenes privadas).
    - `test_livewire_script_editor_validates_and_rejects_cross_tenant_character_and_location_idor` (verificación de errores de validación de Livewire ante payloads manipulados).
  - Suite completa: 241 tests pasando (1.190 aserciones), Pint OK.
- **GitLab Issue:** [jg_filmmaker#6](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/6) cerrado.


