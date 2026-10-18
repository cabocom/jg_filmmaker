# Walkthrough - ISSUE-036: Pydantic v2 Strict Typing y Submodelos de Dominio en jg_plugin_filmmaker

## Resumen de la Solución
Se reemplazaron los tipos débiles `dict[str, Any]` y listas genéricas en las herramientas del plugin por submodelos de dominio tipados y validados en Pydantic v2:
- `CharacterContext`: Modelado riguroso de personajes con campos específicos (`name`, `bio`, `psychology`, `costume_notes`, `role`) y tolerancia `ConfigDict(extra="ignore")` para aceptar metadatos de Eloquent/Laravel (`id`, `uuid`, `created_at`) sin quebrar contratos.
- `LocationContext`: Escenario dramático tipado (`name`, `type`, `time_of_day`, `description`, `lighting_notes`, `sound_notes`).
- `ManualDiffItem`: Registro formal de diffs manuales (`stage`, `original_ai`, `user_override`, `applied_at`).
- `UserLocksModel`: Estructura estricta para `pinned_terms`, `manual_diffs`, `archived_diffs` y `locked_fields`.
- `ScriptBlockItem`: Representación formal de bloques dramáticos para auditoría de agujeros de guion.
- `LLMClientProtocol`: Protocolo con `@runtime_checkable` para eliminar el uso de `Any` en clientes LLM.

## Cambios Realizados

### 1. Modelado en `jg_plugin_filmmaker/tools.py`
- Creados los submodelos `CharacterContext`, `LocationContext`, `ManualDiffItem`, `UserLocksModel`, `ScriptBlockItem`.
- Actualizados los esquemas de argumentos de las 4 herramientas (`TreatmentStructuringArgs`, `ScreenplayFormatterArgs`, `ShotBreakdownDetectorArgs`, `PlotHoleDetectorArgs`) para vincular los nuevos modelos.
- Blindadas las funciones ejecutoras (`execute_*`):
  - Normalización transparente de argumentos recibidos (acepta tanto instancias Pydantic como diccionarios puros o de tests).
  - Sustitución de `except Exception as e:` que exponía `str(e)` crudo al usuario por `logger.exception(...)` interno y mensajes de error limpios y controlados con `error_code` estable en metadatos (`STRUCTURING_PROCESSING_ERROR`, `FORMATTER_PROCESSING_ERROR`, etc.).
  - Conteo estricto y seguro de candados aplicados (`applied_locks_count`).

### 2. Robustez en `jg_plugin_filmmaker/structuring.py`
- En `apply_user_locks`, normalización de `user_locks` con `.model_dump()` o descarte seguro si el tipo recibido no es un diccionario ni modelo Pydantic, impidiendo `AttributeError` o `TypeError` residuales.

### 3. Suite de Pruebas `tests/test_pydantic_strict_typing.py`
- Validaciones de campos requeridos y restricciones de longitud (`min_length=1`, etc.).
- Verificación de tolerancia `extra="ignore"` ante cargas con metadatos de BD Laravel Eloquent.
- Verificación de rechazo de tipos no conformes (`pinned_terms` como string o listas vacías en `blocks`).
- Verificación de ejecución exitosa tanto con dicts como con modelos Pydantic instanciados.

## Verificación de Calidad
- `bash scripts/check.sh` en `jg_plugin_filmmaker`:
  - Ruff linter: 0 errores.
  - Ruff format: código limpio.
  - Pytest: 27 pruebas pasando en 0.11s.
- `php artisan test` en `jg_filmmaker`:
  - 143 pruebas pasando al 100% (686 aserciones).
