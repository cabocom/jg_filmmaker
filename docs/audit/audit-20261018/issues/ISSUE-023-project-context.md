# Actualización de Contexto del Proyecto: ISSUE-023

## Componente Afectado
`jg_filmmaker` (`ScriptEditor`, modelos `Scene`, `ScriptBlock`, `Shot`, `Take`).

## Comportamiento Actualizado
- Asignación atómica de contadores e índices ordinales bajo transacciones `DB::transaction()` con bloqueo `lockForUpdate()`.
- Erradicación de condiciones de carrera y duplicidad de numeraciones en montaje y desglose.
