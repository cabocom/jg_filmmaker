# Project Context: [ISSUE-010] Saneamiento de AgentClientService, observabilidad y control de direct LLM

## Resumen del Cambio
Se eliminó el comportamiento de silenciamiento ciego de errores HTTP en `AgentClientService`, dotando al cliente de un sistema de logging estructurado que reporta códigos de estado y anomalías sin registrar datos sensibles ni PII. Se introdujo `Http::retry` acotado a excepciones de conexión, se bloqueó de manera terminante el bypass a proveedores directos (`callDirectLlm`) en entornos productivos mediante el flag `services.llm.direct_enabled`, se implementó validación del flag de éxito del contrato `ToolResult` y se expuso trazabilidad completa de origen (`source`) y estado de degradación (`degraded`).

## Archivos Modificados / Creados
- `app/Services/JgAgente/AgentClientService.php`: Logging estructurado, reintentos de conexión, bloqueo de direct LLM en producción, saneamiento de respuestas en logs y trazabilidad de degradación.
- `tests/Feature/AgentClientRobustnessTest.php`: Suite de pruebas para fallos de red, logs, flags y rechazo de direct LLM en producción.
- `docs/audit/audit-20261018/issues/ISSUE-010-analysis.md`: Análisis técnico.
- `docs/audit/audit-20261018/issues/ISSUE-010-walkthrough.md`: Guía de implementación.
- `docs/audit/audit-20261018/issues/ISSUE-010-project-context.md`: Este documento de contexto.

## Impacto en Seguridad y Desacoplamiento
- **Desacoplamiento Canónico:** Se respeta la política de que toda inferencia cognitiva en producción debe ser gobernada por `jg_agente`.
- **Privacidad:** Cero filtraciones accidentales de texto de guiones en archivos de log ante respuestas de error de proveedores.
- **Observabilidad Operativa:** Diagnóstico transparente de discrepancias de red, autorizaciones HMAC o caídas del servicio central.
