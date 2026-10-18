# Project Context Update - ISSUE-024

## Componente Afectado
- `jg_filmmaker` (v3.13.0)

## Resumen de Cambios
- Optimización de latencia en peticiones Livewire: eliminación de consulta duplicada de escena activa en `ScriptEditor`.
- Cacheo de agregaciones de telemetría de tokens (`User::getTokensUsedToday`) con invalidación en tiempo real en `AgentRequestLog::booted()`.
- Memoización en memoria de tomas seleccionadas en `RoughCutTimeline`.
