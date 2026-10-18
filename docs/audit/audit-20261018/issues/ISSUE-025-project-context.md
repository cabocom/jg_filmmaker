# Actualización de Contexto del Proyecto: ISSUE-025

## Componente Afectado
`jg_filmmaker` (Modelos `Take`, `Scene`, `Project`, comando `media:prune-orphans`).

## Comportamiento Actualizado
- Limpieza física de ficheros audiovisuales garantizada ante borrados individuales o cascadas de escenas y proyectos.
- Eliminación desacoplada en `DB::afterCommit` evitando inconsistencias si una transacción de base de datos aborta.
- Comando `media:prune-orphans` disponible para auditoría y saneamiento continuo de almacenamiento.
