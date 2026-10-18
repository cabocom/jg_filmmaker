# Walkthrough Técnico: [ISSUE-004] IDOR en `ContextBibleDrawer` y `RoughCutTimeline` (lectura/edición de personajes, localizaciones y tomas ajenas)

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026

---

## 1. Archivos Modificados

### 1.1. Componente del Drawer de la Biblia
- [`jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php):
  - Se añade `#[Locked]` a la propiedad `$project`.
  - En `mount()`, se ejecuta `Gate::authorize('view', $project)`.
  - En `startEditCharacter` y `saveCharacter`, se sustituye `Character::findOrFail` por `$this->project->characters()->findOrFail(...)`.
  - En `startEditLocation` y `saveLocation`, se sustituye `Location::findOrFail` por `$this->project->locations()->findOrFail(...)`.

### 1.2. Componente de Línea de Tiempo y Pre-corte
- [`jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php):
  - Se añade `#[Locked]` a la propiedad `$project`.
  - En `mount()`, `assembleRoughCut()` y `exportRoughCutJson()`, se valida `Gate::authorize('view', $project)`.
  - En `selectTakeForPlayback(string $takeId)`, se valida obligatoriamente que la toma pertenezca a la colección de tomas aprobadas del proyecto (`abort_unless($this->getGoodTakes()->contains('id', $takeId), 404)`).
  - En `render()`, se elimina el fallback `?? Take::with(['shot.scene.location'])->find(...)`, evitando fugas de medios de otros proyectos.

### 1.3. Suite de Pruebas
- [`jg_filmmaker/tests/Feature/ContextBibleAndTimelineIsolationTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/ContextBibleAndTimelineIsolationTest.php):
  - `test_cannot_edit_characters_of_another_project`: comprueba respuesta 404 al intentar editar personajes de proyectos ajenos.
  - `test_cannot_edit_locations_of_another_project`: comprueba respuesta 404 al intentar editar localizaciones de proyectos ajenos.
  - `test_rough_cut_timeline_rejects_cross_tenant_take_playback`: comprueba 404 ante intentos de seleccionar tomas de terceros para reproducción.
  - `test_rough_cut_timeline_does_not_leak_other_project_take_in_render`: comprueba que manipular `currentPlayingTakeId` no filtra vídeos ni notas de otros proyectos en la vista.

---

## 2. Pruebas de Verificación y No Regresión

- **Linter de código:** `./vendor/bin/pint --test` (Passed)
- **Suite de pruebas:** `php artisan test` (95 passed, 533 assertions)
- **Pipeline CI/CD:** `./scripts/check.sh --cicd -p jg_filmmaker` (Passed)
