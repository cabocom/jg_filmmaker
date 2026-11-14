# ISSUE-022 Análisis: Paginación y Optimización de Consultas Administrativas

## 1. Diagnóstico del Problema
- `UserList` y `ProjectManager` realizaban consultas sin paginación (`->get()`) sobre todas las filas de la base de datos en cada renderizado de Livewire.
- `ProjectManager` cargaba la lista completa de todos los usuarios del sistema en cada ciclo de render, incluso cuando el modal de transferencia estaba cerrado.
- `UserDetail` realizaba agregaciones redundantes con múltiples `JOIN` para calcular totales que ya se disponían o que podían consultarse en una sola sentencia indexada.
- Ausencia de índices compuestos para acelerar agregaciones por etapa en bloques de guión (`script_blocks`) y telemetría histórica (`agent_request_logs`).

## 2. Estrategia de Mitigación
1. Implementar `WithPagination` con páginas de 20-25 elementos en `UserList` y `ProjectManager`.
2. Acotar el cálculo de telemetría en `UserList` y `ProjectManager` exclusivamente a los identificadores de la página actual.
3. Diferir la carga de la colección de usuarios para transferencias en `ProjectManager` y `UserDetail`, cargándola solo cuando el modal esté activo.
4. Crear migración con índices `script_blocks(scene_id, stage)` y `agent_request_logs(created_at)`.
