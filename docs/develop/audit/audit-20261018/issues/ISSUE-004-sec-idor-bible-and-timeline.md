# [ISSUE-004] IDOR en `ContextBibleDrawer` y `RoughCutTimeline` (lectura/edición de personajes, localizaciones y tomas ajenas)

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ContextBibleDrawer.php:98,119,138,148`, `app/Livewire/Studio/RoughCutTimeline.php:74-77,147-150`
- **Categoría:** Seguridad

## 1. Descripción del Problema
- `ContextBibleDrawer`: `startEditCharacter`, `saveCharacter`, `startEditLocation`, `saveLocation` usan `Character::findOrFail($id)` / `Location::findOrFail($id)` globales, mientras que `requestDelete*`/`confirmDelete` sí usan `$this->project->characters()->findOrFail()`. La inconsistencia demuestra que el patrón seguro era conocido pero no aplicado. `startEdit*` además **devuelve al cliente** (`editingCharBio`, `editingLocArtNotes`) contenido de la Biblia de otro tenant.
- `RoughCutTimeline::selectTakeForPlayback()` asigna `currentPlayingTakeId` sin validar; `render()` hace fallback `Take::with([...])->find($this->currentPlayingTakeId)` (línea 149) **sin filtrar por proyecto**, entregando `video_url` y notas editoriales de cualquier toma.
- `public ?string $currentPlayingTakeId`, `$filterSceneId` son modificables por el cliente.

## 2. Evidencia de Código
```php
// ContextBibleDrawer
$char = Character::findOrFail($characterId);              // sin $this->project
$this->editingCharBio = $char->bio ?? '';
// RoughCutTimeline
$currentTake = $goodTakes->firstWhere('id', $this->currentPlayingTakeId)
    ?? Take::with(['shot.scene.location'])->find($this->currentPlayingTakeId);   // fuga cross-tenant
```

## 3. Impacto Operativo o de Seguridad
Exposición de biografías, psicologías, notas de arte/iluminación y URLs de vídeo (material audiovisual inédito) de otros autores; sobrescritura de la Biblia ajena.

## 4. Solución Recomendada
```php
$char = $this->project->characters()->findOrFail($characterId);
$loc  = $this->project->locations()->findOrFail($locationId);

// RoughCutTimeline
$currentTake = $goodTakes->firstWhere('id', $this->currentPlayingTakeId);   // sin fallback global
public function selectTakeForPlayback(string $takeId): void
{
    abort_unless($this->getGoodTakes()->contains('id', $takeId), 404);
    $this->currentPlayingTakeId = $takeId;
}
```
Añadir `#[Locked]` a `$project` y a los IDs de estado, `authorize('view', $project)` en `mount()` y tests de aislamiento entre usuarios.
