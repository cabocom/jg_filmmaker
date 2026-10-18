# Análisis de Resolución: ISSUE-006

## 1. Contexto y Diagnóstico
En la auditoría técnica de `jg_filmmaker` se identificaron dos vulnerabilidades de seguridad y discrepancia arquitectónica estrechamente relacionadas:
1. **Medios audiovisuales públicos sin autenticación (Fuga de PI):**
   Las tomas (`takes`) subidas y los renders de premontaje (`rough cut`) se almacenaban en el disco `public` de Laravel (`storage/app/public/media/...`) y se exponían mediante enlaces directos a `/storage/...` sin autenticación. Cualquier usuario o tercero con la URL podía descargar el material inédito eludiendo las directivas de control de acceso.
2. **UUID v7 en lugar de UUID v4 estricto:**
   Laravel 12 por defecto implementa en el trait `HasUuids` el método `newUniqueId()` retornando `Str::uuid7()`. Los UUIDv7 contienen un timestamp de 48 bits en su prefijo haciéndolos ordenados cronológicamente y parcialmente predecibles, contradiciendo la documentación oficial de la spec (`docs/01_architecture.md`) que exige UUIDv4 estricto y debilitando la defensa anti-enumeración.

## 2. Decisiones de Diseño
- **Sustitución de Trait UUID:** En los 13 modelos Eloquent del sistema (`User`, `Project`, `Scene`, `ScriptBlock`, `Shot`, `Take`, `Character`, `Location`, `AgentRequestLog`, `SecurityBreachLog`, `UserActivityLog`, `OneTimePassword`, `TokenPricingRate`) sustituir `use Illuminate\Database\Eloquent\Concerns\HasUuids;` por `use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;`. Esto asegura que `newUniqueId()` genere UUID versión 4 (RFC 4122) compatible con la spec.
- **Almacenamiento Privado (`local`):** Redirigir el almacenamiento de tomas y renders de premontaje al disco privado `local` (`storage/app/private/media/...`), no expuesto mediante symlinks públicos de Nginx.
- **Rutas de Streaming Protegidas con Control de Autorización:**
  - `GET /media/takes/{take}` (`media.take`)
  - `GET /media/projects/{project}/renders/{filename}` (`media.render`)
  Ambas rutas protegidas con middleware `auth` y verificación estricta de tenencia (`$project->user_id === Auth::id()`), devolviendo `403 Forbidden` a usuarios no autorizados y `401/302` a visitantes anónimos.
- **Retrocompatibilidad Transaccional:** Soporte de lectura con fallback seguro tanto en disco `local` como en `public` para archivos preexistentes en entornos en ejecución.

## 3. Plan de Verificación
- Pruebas automatizadas de arquitectura para comprobar que la versión de UUID generada en todos los modelos es exactamente 4 (`$uuid->getVersion() === 4`).
- Pruebas de acceso no autenticado (redirección a login).
- Pruebas de intento IDOR entre usuarios cruzados (403 Forbidden).
- Pruebas de acceso legítimo por parte del propietario (200 OK con streaming).
- Ejecución completa de la suite de tests y linter (`pint --test`).
