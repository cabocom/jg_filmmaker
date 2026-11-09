# [ISSUE-022] Consultas sin Paginar y Cuádruple Agregación SQL sobre `agent_request_logs` en `UserDetail::render()` (Pendiente de `audit-20261018 / ISSUE-022`)

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Admin/UserDetail.php:86-168`
- **Categoría:** Rendimiento / Escalabilidad

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-022`) se solicitó paginar las vistas administrativas y consolidar las múltiples consultas de agregación sobre `agent_request_logs`. Mientras `UserList` y `ProjectManager` sí incorporaron `WithPagination`, el componente `UserDetail::render()` quedó sin optimizar:

1. **Listado de Proyectos del Usuario sin Paginar:** Ejecuta `$this->user->projects()->withCount(['scenes', 'characters', 'locations'])->orderBy('created_at', 'desc')->get()`, cargando todos los proyectos del usuario en memoria y calculando el desglose de bloques para todos ellos en cada render.
2. **Cuatro Consultas de Agregación Separadas sobre `agent_request_logs` en el Mismo Render:**
   - **Query 1 (líneas 109-121):** `DB::table('agent_request_logs')->whereIn('project_id', $projectIds)->groupBy('project_id')->get()` para obtener peticiones, tokens y coste por proyecto.
   - **Query 2 (líneas 124-125):** `DB::table('agent_request_logs')->join('projects', ...)->where('projects.user_id', $this->user->id)->sum('total_tokens')`.
   - **Query 3 (líneas 127-128):** `DB::table('agent_request_logs')->join('projects', ...)->where('projects.user_id', $this->user->id)->sum('cost_usd')`.
   - **Query 4 (línea 130):** `$this->user->getTokensUsedToday()`.
   Además, las Queries 2 y 3 siguen haciendo `JOIN projects WHERE projects.user_id = ?` en lugar de usar la columna indexada `agent_request_logs.user_id` (añadida en `ISSUE-017`) o sumar directamente en memoria los valores ya devueltos por la Query 1 (`$telemetryByProject->sum('total_tokens')` y `$telemetryByProject->sum('total_cost_usd')`).

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Admin/UserDetail.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/UserDetail.php#L109-L131):
```php
        $telemetryByProject = DB::table('agent_request_logs')
            ->whereIn('project_id', $projectIds)
            ->select(
                'project_id',
                DB::raw('COUNT(*) as requests_count'),
                DB::raw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens'),
                DB::raw('COALESCE(SUM(completion_tokens), 0) as completion_tokens'),
                DB::raw('COALESCE(SUM(total_tokens), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(cost_usd), 0) as total_cost_usd')
            )
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $totalUserTokens = (int) DB::table('agent_request_logs')
            ->join('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->where('projects.user_id', $this->user->id)
            ->sum('agent_request_logs.total_tokens');

        $totalUserCostUsd = (float) DB::table('agent_request_logs')
            ->join('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->where('projects.user_id', $this->user->id)
            ->sum('agent_request_logs.cost_usd');
```

## 3. Impacto Operativo o Riesgo
- **Consultas Redundantes y Discrepancia tras Transferencias:** Las Queries 2 y 3 escanean dos veces la tabla `agent_request_logs` con `JOIN projects` en cada render de `/admin/users/{user}`, y además atribuyen el histórico de gasto al propietario actual del proyecto (`projects.user_id`) en vez de al usuario que ejecutó el gasto (`agent_request_logs.user_id`).

## 4. Solución o Mejora Recomendada
Unificar las Queries 2 y 3 en una sola consulta directa sobre el índice `agent_request_logs.user_id` y paginar los proyectos del usuario con `WithPagination`:

```php
        $userTotals = DB::table('agent_request_logs')
            ->where('user_id', $this->user->id)
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(cost_usd), 0) as total_cost_usd')
            ->first();

        $totalUserTokens = (int) ($userTotals->total_tokens ?? 0);
        $totalUserCostUsd = (float) ($userTotals->total_cost_usd ?? 0.0);
```

