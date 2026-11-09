# [ISSUE-001] IDOR Cross-Tenant en Acondicionamiento Multimodal de Storyboards (Personajes y Localizaciones)

- **Severidad:** Crítica
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/StoryboardService.php:226-284`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:105-107, 1726-1836`
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

