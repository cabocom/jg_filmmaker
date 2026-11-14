# Project Context: [ISSUE-007] Eliminación de credenciales por defecto, fortalecimiento de Compose y fail-fast en arranque

## Resumen del Cambio
Se mitigó la presencia de credenciales hardcodeadas y defaults inseguros en el ecosistema Docker y de arranque de Laravel. Se eliminaron valores fijos de `APP_KEY` y `JG_AGENTE_SECRET` en `docker-compose.yml`, requiriendo su definición obligatoria mediante `${VAR:?error}`. Se actualizaron los valores por defecto de producción en Docker y `.env.example`. En `entrypoint.sh`, se impidió el arranque si falta `APP_KEY` en producción, se suprimió la tolerancia ciega a errores de migración (`|| true`) y se bloqueó la siembra de usuarios demo en producción. En `AppServiceProvider::boot()`, se implementó validación fail-fast que rechaza claves y secretos inseguros o conocidos en producción.

## Archivos Modificados / Creados
- `docker-compose.yml`: Eliminación de secretos quemados; variables obligatorias y defaults de producción seguros.
- `.env.example`: Establecimiento de `APP_DEBUG=false` y `LOG_LEVEL=info`.
- `docker/entrypoint.sh`: Validación de `APP_KEY` en producción, migraciones estrictas y protección contra `SEED_DEMO` en producción.
- `app/Providers/AppServiceProvider.php`: Chequeo fail-fast de `APP_KEY` y `JG_AGENTE_SECRET` en producción contra claves conocidas/débiles.
- `tests/Unit/SecurityFailFastTest.php`: Suite unitaria que valida el comportamiento fail-fast.
- `docs/audit/audit-20261018/issues/ISSUE-007-analysis.md`: Análisis técnico previo.
- `docs/audit/audit-20261018/issues/ISSUE-007-walkthrough.md`: Guía de implementación.
- `docs/audit/audit-20261018/issues/ISSUE-007-project-context.md`: Este documento de contexto.

## Impacto en Seguridad y Despliegue
- **Cero Secretos Hardcodeados:** Desaparición de cualquier clave simétrica o HMAC pública en el control de versiones.
- **Fail-Fast Operativo:** Erradicación de contenedores que arrancan de manera silenciosa con credenciales por defecto o con migraciones fallidas.
- **Protección de Datos:** Imposibilidad de sobreescribir o sembrar datos de prueba/demo en entornos de producción.
