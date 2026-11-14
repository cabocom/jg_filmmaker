# Project Context - ISSUE-014: Normalización de Telemetría y Desacoplamiento de `user_locks`

## 1. Identificación del Cambio
- **Issue:** ISSUE-014 (Extracción de telemetría IA dentro de `user_locks` y eliminación de bloat recursivo).
- **Subsistema:** `jg_filmmaker` (Pipeline de IA, Telemetría y Livewire Studio).
- **Alcance:** Base de datos (`agent_request_logs.meta`, limpieza de `script_blocks.user_locks`), saneamiento de payloads en `ScriptPipelineService` y lectura desacoplada en `ScriptEditor`.

## 2. Decisiones Arquitectónicas
- **Normalización de Telemetría Técnica:** La información operativa generada por los modelos de lenguaje (prompts de sistema, prompts de usuario, respuestas en bruto, tokens, coste estimado, desglose de pasos y duración en milisegundos) pertenece al dominio de telemetría e infraestructura, no al modelo de autoría del cineasta. Se persiste en la columna `meta` de la tabla `agent_request_logs`.
- **Pureza de `script_blocks.user_locks`:** Esta columna queda exclusivamente reservada a los candados y decisiones creativas del usuario (`pinned_terms`, `manual_diffs`, `locked_fields`, justificaciones y marcas de agujeros de guión). Ningún dato de telemetría se almacena en `user_locks`.
- **Filtro de Salida hacia el Agente:** `ScriptPipelineService::sanitizeUserLocksForAgent()` intercepta las cargas útiles enviadas a `structureTreatment` y `formatScreenplay`, garantizando que únicamente se envíen los campos de gobernanza necesarios, eliminando sobrecostes de tokens por peticiones infladas.
- **Trazabilidad en Estudio sin Acoplamiento:** La funcionalidad de inspección de IA en `ScriptEditor::openAiTrace()` ahora recupera la telemetría correspondiente desde `AgentRequestLog`, preservando la experiencia de auditoría para el usuario sin impactar el rendimiento de la base de datos.

## 3. Estado de la Suite de Calidad
- **Tests automatizados:** 124 tests pasando (635 aserciones).
- **Linter de código:** Laravel Pint 100% limpio (0 advertencias).
- **Pipeline CI/CD:** `./scripts/check.sh --cicd -p jg_filmmaker` exitoso.
