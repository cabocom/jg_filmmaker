# Actualización de Contexto del Proyecto: ISSUE-020

## Componente Afectado
`jg_filmmaker` (`ScriptPipelineService`, `Location`, `Character`).

## Comportamiento Actualizado
- Localizaciones de la Biblia son inmutables ante cambios de sluglines: nunca se eliminan ni se sobreescriben automáticamente.
- Detección sintáctica de personajes libre de listas hardcodeadas de nombres propios.
- Rendimiento optimizado mediante precarga de mapa de localizaciones en memoria.
