# [ISSUE-019] `ScriptEditor::mount()` ejecuta escrituras y consultas N+1 en cada carga (y tras cada borrado de escena)

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:191-214,772-774`, `app/Services/ScriptPipelineService.php:338-437,539-642`
- **Categoría:** Rendimiento

## 1. Descripción del Problema
`mount()` (una petición GET) llama a `syncProjectCharacters()` y `syncProjectLocations()`, que:
- Cargan **todos** los textos del proyecto (`scenes.scriptBlocks`, concatenando `raw_text + structured_text + screenplay_content` en un `string`) → memoria O(tamaño del guion) por visita.
- Aplican ~8 expresiones regulares (nombres propios, roles, Fountain) sobre ese texto completo.
- Por **cada candidato nuevo** hacen `whereRaw('LOWER(name)=?')->exists()` + `create()` (N+1 de escrituras); `syncProjectLocations` hace, por cada escena, `find()`, `first()`, `count()`, `update()`, `create()`.
- **Mutan la BD en una petición de lectura** (GET con efectos secundarios, incompatible con caché, prefetch o abrir el proyecto en modo lectura) y se repite en cada recarga/navegación; `confirmDeleteScene` los vuelve a ejecutar.
- No hay transacción: dos pestañas abiertas a la vez crean personajes duplicados (la unicidad `LOWER(name)` no coincide con el índice `unique(project_id,name)` sensible a mayúsculas → duplicados en SQLite/PG, excepción en MySQL).

## 2. Evidencia de Código
```php
public function mount(Project $project): void
{
    $this->project = $project;
    $pipeline = app(ScriptPipelineService::class);
    $pipeline->syncProjectCharacters($this->project);     // carga todos los textos + escribe
    $pipeline->syncProjectLocations($this->project);      // bucle con queries por escena
```
```php
foreach ($candidates as $name) { ... $project->characters()->whereRaw('LOWER(name) = ?', [$lower])->exists(); ... create(...) }
```

## 3. Impacto Operativo o de Seguridad
Latencia creciente con el tamaño del guion, bloqueos de escritura en SQLite durante una simple visita (ver ISSUE-033), duplicados y estados no deterministas.

## 4. Solución Recomendada
- Quitar la sincronización de `mount()`; ejecutarla en un evento explícito (tras `createNewBlock/saveBlock/confirmDeleteScene`) o en un Job encolado debounced por proyecto.
- Calcular candidatos sobre el bloque modificado, no sobre todo el proyecto.
- Precargar nombres existentes una vez (`pluck('name')`) y usar `upsert` con índice único normalizado (`name_normalized`).
- Envolver en transacción y usar `Cache::lock("sync:{$project->id}")`.
- Medir con `DB::listen`/Telescope; test que afirme nº máximo de queries en `mount()`.
