# Análisis Técnico: [ISSUE-004] IDOR en `ContextBibleDrawer` y `RoughCutTimeline` (lectura/edición de personajes, localizaciones y tomas ajenas)

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidad:** CWE-639 (Authorization Bypass Through User-Controlled Key / IDOR) / CWE-200 (Information Disclosure)

---

## 1. Diagnóstico del Estado Previo

1. **Inconsistencia de Ámbito en `ContextBibleDrawer`:**
   - En las operaciones de eliminación (`requestDelete*`, `confirmDelete`), el componente aplicaba el patrón seguro `$this->project->characters()->findOrFail(...)`.
   - Sin embargo, en `startEditCharacter($id)` y `saveCharacter()` se utilizaba `Character::findOrFail($id)` de forma global. De igual forma, en `startEditLocation($id)` y `saveLocation()` se utilizaba `Location::findOrFail($id)`.
   - Esto permitía a un atacante enviar un identificador UUID de un personaje o localización de otro usuario en `startEditCharacter` y recibir en el cliente la biografía y notas de vestuario (`editingCharBio`, `editingCharCostume`), o bien sobrescribir dicha información en `saveCharacter`.

2. **Fuga Cross-Tenant y Fallback Inseguro en `RoughCutTimeline`:**
   - El método `selectTakeForPlayback(string $takeId)` asignaba `$this->currentPlayingTakeId = $takeId` sin validar si la toma pertenecía al proyecto actual o estaba aprobada.
   - En el método `render()`, si la toma no estaba en la colección filtrada `$goodTakes`, ejecutaba un fallback global:
     ```php
     $currentTake = $goodTakes->firstWhere('id', $this->currentPlayingTakeId)
         ?? Take::with(['shot.scene.location'])->find($this->currentPlayingTakeId);
     ```
   - Este fallback devolvía la toma de cualquier proyecto del sistema, renderizando el reproductor de vídeo con `video_url`, plano, notas editoriales y datos de localización confidenciales de otro autor.

3. **Propiedades de Proyecto sin Bloqueo:**
   - La propiedad `public Project $project` en ambos componentes carecía de `#[Locked]`, y sus métodos `mount()` no ejecutaban `Gate::authorize('view', $project)`.

---

## 2. Alternativas Evaluadas

1. **Mantener fallback en `RoughCutTimeline` pero añadiendo scope de proyecto:**
   - *Descartada:* En la vista de montaje, el reproductor de corte solo debe reproducir tomas válidas (`is_good_take = true`) de este proyecto. Un fallback a tomas arbitrarias fuera de la línea de tiempo es redundante e inseguro.
2. **Aplicar ámbito estricto de `$this->project` en todos los métodos de ambos componentes (Opción Seleccionada):**
   - *Ventajas:*
     - En `ContextBibleDrawer`: toda consulta a personajes y localizaciones se realiza mediante `$this->project->characters()` y `$this->project->locations()`.
     - En `RoughCutTimeline`: se restringe `selectTakeForPlayback` a tomas presentes en `$this->getGoodTakes()`, y se elimina el fallback global en `render()`.
     - Se protege `$project` con `#[Locked]` y se autoriza el acceso en `mount()`.

---

## 3. Plan de Implementación

1. **Refactorizar `ContextBibleDrawer` (`app/Livewire/Studio/ContextBibleDrawer.php`):**
   - Importar `Livewire\Attributes\Locked` y `Illuminate\Support\Facades\Gate`.
   - Marcar `#[Locked] public Project $project;`.
   - En `mount(Project $project)`: añadir `Gate::authorize('view', $project);`.
   - En `startEditCharacter` y `saveCharacter`: usar `$this->project->characters()->findOrFail(...)`.
   - En `startEditLocation` y `saveLocation`: usar `$this->project->locations()->findOrFail(...)`.

2. **Refactorizar `RoughCutTimeline` (`app/Livewire/Studio/RoughCutTimeline.php`):**
   - Importar `Livewire\Attributes\Locked` y `Illuminate\Support\Facades\Gate`.
   - Marcar `#[Locked] public Project $project;`.
   - En `mount()`: añadir `Gate::authorize('view', $project);`.
   - En `selectTakeForPlayback(string $takeId)`: validar que `$this->getGoodTakes()->contains('id', $takeId)`, o abortar con 404.
   - En `render()`: eliminar el fallback a `Take::find(...)`.

3. **Pruebas Automatizadas de Aislamiento (`tests/Feature/ContextBibleAndTimelineIsolationTest.php`):**
   - Comprobar que `startEditCharacter` y `startEditLocation` con IDs de otros proyectos arrojan 404.
   - Comprobar que `selectTakeForPlayback` con tomas de otros proyectos arroja 404 y no expone el vídeo.
