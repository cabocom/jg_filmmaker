# [ISSUE-024] Render Livewire carga el grafo completo del proyecto en cada interacción y el layout agrega tokens en cada petición

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:1898-1931`, `app/Livewire/Studio/RoughCutTimeline.php:142-167`, `resources/views/layouts/app.blade.php:113-114`, `resources/views/livewire/studio/script-editor.blade.php:1939`, `app/Models/User.php:103-110`
- **Categoría:** Rendimiento

## 1. Descripción del Problema
- `ScriptEditor::render()` se ejecuta tras **cada** acción Livewire (clic en pestaña, selección de bloque, apertura de modal) y hace eager loading de `scenes → location, scriptBlocks → shots → takes` de todo el proyecto, más una segunda consulta `Scene::with(['location','scriptBlocks.shots.takes','shots.takes'])->find($activeSceneId)` que **repite** los mismos datos de la escena activa. Sin paginación/virtualización: guiones largos devuelven miles de bloques con `raw_text/structured_text/screenplay_content/user_locks` completos y serializan todo el DOM a cada request.
- La vista (`script-editor.blade.php` ~2000 líneas) recorre colecciones y llama a métodos de modelo (`isStageLocked`, `hasManualOverride`, `getPlotHoleStage`), que decodifican `user_locks` JSON repetidamente por bloque/render.
- `RoughCutTimeline::render()` invoca `getGoodTakes()` y además `mount()`, `setSceneFilter`, `exportRoughCutJson` lo vuelven a llamar (1 query pesada con `whereHas` + 2 `join` + eager load por llamada), y `$this->project->scenes()` otra vez.
- El layout ejecuta `auth()->user()->getTokensAvailableToday()` en **cada render** → doble JOIN `agent_request_logs ⨝ projects` con `SUM` (`getTokensUsedToday()` se calcula dos veces: en `getTokensAvailableToday` y en la vista de plot-holes).
- `getGoodTakes()` mezcla `whereHas` con `join` sobre las mismas tablas (doble filtrado) y `with(...)` + `select('takes.*')`, que no siempre preserva el orden de las relaciones; el `whereHas('shot.scene', ... where('id', $sceneId))` filtra `scenes.id`.

## 2. Evidencia de Código
```php
$activeScene = Scene::with(['location','scriptBlocks.shots.takes','shots.takes'])->find($this->activeSceneId);
...
$userTokensAvail = auth()->user()->getTokensAvailableToday();    // layout, cada render
```

## 3. Impacto Operativo o de Seguridad
Latencia y consumo de memoria crecientes por interacción, payloads Livewire enormes (HTML diff de todo el guion), carga de BD que escala con (usuarios × interacciones × tamaño de guion).

## 4. Solución Recomendada
- Dividir en componentes hijos con `wire:key` y carga perezosa por escena (`#[Lazy]`), cargar bloques sólo para escenas expandidas y evitar `activeScene` duplicado (reusar la colección ya cargada).
- Seleccionar sólo columnas necesarias en la vista de lista y cargar el texto completo al abrir el drawer.
- Memoizar con `#[Computed]` (`goodTakes`, `scenes`) para reutilizarlos en un mismo ciclo.
- Cachear `tokensUsedToday` por usuario (`Cache::remember("tokens:{$id}", 30, ...)`) o contador incremental, e invalidar al registrar telemetría.
- Cast `AsArrayObject`/accessor con memoización para `user_locks`.
- `Model::preventLazyLoading()` en dev y test de nº de queries.
