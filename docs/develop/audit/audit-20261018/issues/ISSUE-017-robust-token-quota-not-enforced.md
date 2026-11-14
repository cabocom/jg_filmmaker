# [ISSUE-017] El control de cuota diaria de tokens sólo se aplica a la auditoría de agujeros y es vulnerable a check-then-act

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:929-1048,1565-1574`, `app/Models/User.php:103-126`, `app/Services/AgentTelemetryService.php:21-70`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
- `hasDailyTokensAvailable()` sólo se invoca en `analyzePlotHoles()`. `structureBlock`, `formatBlockToScreenplay` y `breakdownBlockShots` (las llamadas más frecuentes y costosas) **no comprueban la cuota**: un usuario con cuota agotada sigue consumiendo IA sin límite. La cuota documentada en `docs/08` no se aplica de verdad.
- El umbral es `hasDailyTokensAvailable(10)`: se exige disponer de 10 tokens, y luego `analyzePlotHoles` envía **todo el proyecto en 3 fases** sin estimar el coste previo ni cortar a mitad. Un guion largo consume órdenes de magnitud sobre el límite.
- Check-then-act sin atomicidad: peticiones simultáneas pasan el chequeo a la vez.
- Los tokens se **estiman** (`strlen/4`) salvo que el agente devuelva `usage`; se registran después de la llamada, y si ésta falla (excepción) no se registra nada aunque el proveedor haya cobrado.
- El consumo está ligado a `projects.user_id`: tras `transferProject` el historial de consumo "cambia de dueño" y el usuario original recupera cupo (elusión).
- La cuota se calcula con `now()->startOfDay()` en la zona horaria de la app; `getTokensUsedToday()` se ejecuta con JOIN en **cada render** del layout (ver ISSUE-024).

## 2. Evidencia de Código
```php
if ($user && ! $user->hasDailyTokensAvailable(10)) { ...return; }       // únicamente aquí
...
public function structureBlock(string $blockId, ScriptPipelineService $pipelineService): void
{   $block = ScriptBlock::findOrFail($blockId);   ...   $pipelineService->transitionToStructured($block);   // sin cuota
```

## 3. Impacto Operativo o de Seguridad
Sobrecoste económico no acotado en el proveedor LLM (cualquier usuario puede agotar el presupuesto), DoS financiero y distorsión de métricas administrativas.

## 4. Solución Recomendada
- Mover el control a un único punto (`AgentClientService::callTool` o middleware de pipeline) con `QuotaExceededException`.
- Reserva atómica de tokens estimados antes de la llamada y reconciliación posterior (`UPDATE users SET tokens_reserved = tokens_reserved + ? WHERE ... AND ...` o `Cache::lock`).
- Guardar `user_id` en `agent_request_logs` para imputación correcta tras transferencias.
- Límite de tamaño por petición (bloques/caracteres) y por proyecto en auditoría; chunking.
- Registrar consumo también en el camino de error.
- Tests: usuario sin cupo → `structureBlock` rechazado.
