# [ISSUE-022] Consultas administrativas sin paginar, agregaciones duplicadas y sin índices adecuados

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Admin/UserList.php:22-46`, `app/Livewire/Admin/ProjectManager.php:101-163`, `app/Livewire/Admin/UserDetail.php:108-163`, `database/migrations/2026_10_10_150005_create_agent_request_logs_table.php:25-26`, `2026_10_09_200005_create_script_blocks_table.php:25-27`
- **Categoría:** Rendimiento

## 1. Descripción del Problema
- `UserList`, `ProjectManager` y `UserDetail` hacen `->get()` sin paginación de **todos** los usuarios/proyectos y los re-ejecutan en cada render Livewire (cada tecla del buscador `search` sin `wire:model.live.debounce` implica nueva consulta completa y las 3 agregaciones).
- `ProjectManager::render()` carga además `User::withCount('projects')->get()` completo sólo para el selector del modal y ejecuta el `JOIN scenes ⨝ script_blocks GROUP BY project_id, stage` en cada render aunque el modal esté cerrado.
- Las agregaciones `SUM/COUNT` sobre `agent_request_logs` unen con `projects` y agrupan por `user_id`: el índice existente es `(project_id, created_at)`; falta `created_at` solo / `(user_id, created_at)` (no hay `user_id` en el log, ver ISSUE-017) para la cuota diaria; en `script_blocks` no hay índice `(scene_id, stage)` para el `GROUP BY`.
- Búsqueda `LIKE '%term%'` sobre `title`, `name`, `email` con `orWhereHas` (sub-selects correlacionados) sin índice usable → full scan.
- `UserDetail` ejecuta tres veces la misma agregación sobre `agent_request_logs` (por proyecto, total tokens, total coste) y `getTokensUsedToday()` una vez más por JOIN.
- La métrica de coste usa `decimal:6` + `SUM` y `(float)` → pérdida de precisión acumulada.

## 2. Evidencia de Código
```php
$users = User::withCount('projects')->orderBy('created_at', 'desc')->get();
$telemetryByUser = DB::table('agent_request_logs')->join('projects', ...)->groupBy('projects.user_id')->get()->keyBy('user_id');
...
$projects = $projectsQuery->get();     // sin paginate()
```

## 3. Impacto Operativo o de Seguridad
Degradación del panel a medida que crece la base (saturación de PHP-FPM/SQLite, ver ISSUE-033), consumo de memoria alto y UI lenta; no hay N+1 clásico sobre relaciones, pero sí agregaciones costosas repetidas.

## 4. Solución Recomendada
- `->paginate(25)` + `WithPagination`; `wire:model.live.debounce.400ms` en el buscador.
- Calcular las métricas de IA/bloques sólo para los IDs de la página y cachearlas (`Cache::remember` 60 s) o mantener tabla de resumen diaria (`ai_usage_daily`).
- Cargar usuarios del modal bajo demanda (cuando `showTransferModal`) con `Select` con búsqueda remota.
- Índices: `agent_request_logs(created_at)`, `agent_request_logs(user_id, created_at)`, `script_blocks(scene_id, stage)`; búsqueda con `title LIKE 'term%'` o FTS.
- Un solo `GROUP BY` para totales + por proyecto con `ROLLUP`/consulta conjunta.
