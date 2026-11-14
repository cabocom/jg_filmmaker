# Contexto de Proyecto Actualizado: ISSUE-006

## Componentes y Arquitectura Modificada

### Modelos Eloquent (`jg_filmmaker/app/Models`)
- Todos los modelos Eloquent de dominio (`User`, `Project`, `Scene`, `ScriptBlock`, `Shot`, `Take`, `Character`, `Location`, `AgentRequestLog`, `SecurityBreachLog`, `UserActivityLog`, `OneTimePassword`, `TokenPricingRate`) implementan el trait `Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids`, garantizando que todas las claves primarias se generen como UUIDv4 (RFC 4122) y eliminando el riesgo de ordenación temporal y predictibilidad de UUIDv7.

### Almacenamiento y Streaming Audiovisual
- El almacenamiento de medios inéditos (tomas grabadas por usuarios y pre-cortes de edición generados por FFmpeg) queda recluido en el disco privado `local` (`storage/app/private/media/...`), impidiendo cualquier acceso directo público a través de Nginx.
- Se implementó `App\Http\Controllers\MediaStreamController` para orquestar la entrega segura y con control de tenencia (`auth` + comprobación de `$project->user_id === Auth::id()`).
- Las URLs consumidas por los componentes Livewire (`Take::video_url` y `RoughCutAssemblyService::video_url`) resuelven a rutas internas seguras (`route('media.take')` y `route('media.render')`).

## Métricas de Calidad
- 153 tests unitarios y de integración pasando al 100% (21 aserciones específicas en `UuidV4AndMediaSecurityTest`).
- Código formateado bajo estándar Laravel Pint (0 errores).
