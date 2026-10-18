# [ISSUE-014] La telemetría IA (prompts y respuestas completas) se guarda dentro de `user_locks` y se reenvía al agente en cada ejecución

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/ScriptPipelineService.php:68-71,155-158,244-247`, `app/Services/JgAgente/AgentClientService.php:1024-1038`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
Tras cada llamada se guarda `$userLocks['ai_telemetry'][stage] = $telemetry`, que incluye `system_prompt`, `user_prompt`, `raw_response`, `output`, `characters_context`, `location_context` y el propio `user_locks`. Ese JSON se persiste en la columna `user_locks` y **en la siguiente regeneración se vuelve a enviar íntegro** como `user_locks` al agente (`structureTreatment(... userLocks: $userLocks)`), que a su vez lo incrusta en el telemetry (`'user_locks' => $payload['user_locks']`) → anidamiento recursivo con crecimiento exponencial en cada regeneración.

Consecuencias: (1) la fila de `script_blocks` crece sin límite (`jsonb` TOAST / SQLite TEXT), (2) cada petición lleva kilobytes de telemetría histórica al agente (tokens/latencia), (3) la telemetría con textos completos mezcla datos operativos con gobernanza del usuario; (4) `AgentTelemetryService` estima tokens por `strlen/4` sobre ese payload inflado, distorsionando el coste y la cuota.

## 2. Evidencia de Código
```php
$userLocks['ai_telemetry']['structured'] = $telemetry;      // prompts + respuestas + user_locks anidado
...
'user_locks' => $userLocks,                                  // persistido y reenviado
```
```php
'user_locks' => $payload['user_locks'] ?? [],                // telemetría anida los locks anteriores
```

## 3. Impacto Operativo o de Seguridad
Degradación de rendimiento y sobrecoste de tokens por regeneración, cuotas diarias consumidas antes de lo previsto, tamaño de BD creciente y exposición innecesaria de prompts (propiedad intelectual) en una columna pensada para preferencias del autor.

## 4. Solución Recomendada
- Tabla `block_ai_runs` (block_id, stage, provider, model, duration_ms, tokens, prompt_hash, output_excerpt, created_at) y guardar allí la telemetría; en `user_locks` sólo `pinned_terms`, `manual_diffs`, `locked_fields`, `plot_hole_stage`, `justified_plot_holes`.
- Filtrar siempre lo que se envía al agente: `Arr::only($userLocks, ['pinned_terms','manual_diffs','locked_fields'])`.
- No incluir `user_locks` ni prompts completos en `lastTelemetry` (guardar hash/longitud).
- Migración para limpiar `ai_telemetry` existente.
