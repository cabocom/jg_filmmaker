# Análisis Técnico - ISSUE-033: Erradicación de Discrepancias entre Especificaciones Técnicas y Código Real

## 1. Contexto y Diagnóstico
Durante la auditoría se detectaron 14 divergencias clave entre la documentación técnica histórica (`docs/`, `docs/specs/`) y la implementación viva:
1. Declaración de claves UUIDv4 cuando Laravel 12 generaba UUIDv7 (resuelto en ISSUE-006).
2. Afirmación de aislamiento por UUID sin políticas de autorización (resuelto en ISSUE-003/004/005 con `ProjectPolicy` y `#[Locked]`).
3. Algoritmo de OTP anunciado como SHA-256 cuando se usaba bcrypt lento en bucle (resuelto en ISSUE-008 con HMAC-SHA256).
4. Exposición de OTP de desarrollo basado en IP de cliente (resuelto en ISSUE-002 con `APP_ENV=local && APP_DEBUG=true`).
5. Herramientas del agente: documentación contemplaba 3 herramientas cuando el cliente consumía 4 (resuelto en ISSUE-011 con `plot_hole_detector`).
6. Contrato de la Biblia dramática: discrepancias en campos (`characters_context` vs atributos canónicos `bio`, `psychology`, `costume_notes`, resuelto en ISSUE-012).
7. Ausencia de la clave `config('services.filmmaker.mock_ffmpeg')` en `config/services.php`.

## 2. Solución Diseñada
1. **Declaración explícita de configuraciones huérfanas:** Registro de `services.filmmaker.mock_ffmpeg` en `config/services.php`.
2. **Foto real y reconciliación de specs:** Actualización de la documentación viva y `PROJECT_CONTEXT.md` reflejando con exactitud matemática el estado vigente del código tras la resolución de los issues.

## 3. Estrategia de Pruebas
- Verificación de carga de configuración con `config('services.filmmaker.mock_ffmpeg')`.
- Ejecución de la suite completa de 191 tests asegurando 0 regresiones.
