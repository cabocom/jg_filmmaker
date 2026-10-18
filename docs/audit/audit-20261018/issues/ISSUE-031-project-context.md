# Project Context Update - ISSUE-031

## Componente Afectado
- `jg_filmmaker` (v3.14.0)

## Resumen de Cambios
- Robustecimiento del protocolo HMAC en `AgentClientService`: soporte de identificador de clave (`X-Key-ID`), serialización segura con `JSON_THROW_ON_ERROR` e inyección de `X-Correlation-ID` en el logging estructurado.
