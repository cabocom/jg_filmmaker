# Actualización de Contexto del Proyecto: ISSUE-022

## Componente Afectado
`jg_filmmaker` (`UserList`, `ProjectManager`, `UserDetail`, esquema BD).

## Comportamiento Actualizado
- Consolas administrativas con paginación optimizada a 20-25 filas.
- Métricas y agregaciones computadas exclusivamente para los identificadores de la página visible.
- Carga de usuarios para transferencias completamente diferida a demanda.
- Nuevos índices compuestos en base de datos para acelerar el agrupamiento por etapas y telemetría histórica.
