# [ISSUE-036] Pydantic v2 poco estricto: `dict[str, Any]` sin esquema, funciones `execute_*` que aceptan datos sin validar y `except Exception` que oculta errores

- **Severidad:** Media
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/tools.py:26-74,80-181`, `jg_plugin_filmmaker/structuring.py:35-72`
- **Categoría:** Calidad de Código

## 1. Descripción del Problema
- Los modelos usan `list[dict[str, Any]]` y `dict[str, Any]` para `characters_context`, `location_context` y `user_locks`: el contrato real (claves `name`, `bio`, `pinned_terms`, `manual_diffs[{stage,original_ai,user_override}]`, `locked_fields`) no está tipado ni validado. Es la causa raíz de que Laravel y el plugin diverjan (ISSUE-012) sin que nada falle.
- No se usan `ConfigDict(extra='forbid')`, `Field(max_length=...)`, `Literal['structured','screenplay']` para `stage`, ni validadores (`field_validator`) para `pinned_terms: dict[str, str]`.
- Las funciones `execute_treatment_structuring(...)` reciben argumentos crudos y los usan directamente (`len(pinned) + len(diffs)`); si `pinned_terms` es una lista/`None`/cadena se produce `TypeError` que cae en `except Exception` → `ToolResult(success=False, error="Fallo al estructurar tratamiento: ...")`, con mensaje que incluye `str(e)` (puede filtrar rutas/internos al cliente) y **sin `logger.exception`** ni código de error estructurado.
- `applied_locks_count` cuenta `pinned + diffs` sin verificar que se aplicaron; la métrica es "locks recibidos", no "aplicados".
- La anotación `Any` para `llm_client` evita tipado (debería ser un `Protocol`).
- `read_only=True`/`ToolType.OBSERVER`/`RiskLevel.LOW` correctos para tools sin efectos; consistente.

## 2. Evidencia de Código
```python
characters_context: list[dict[str, Any]] | None = Field(default=None, ...)
user_locks: dict[str, Any] | None = Field(default=None, ...)
...
except Exception as e:
    return ToolResult(success=False, output="", error=f"Fallo al estructurar tratamiento: {e}")
```

## 3. Impacto Operativo o de Seguridad
Errores de contrato silenciosos entre servicios, mensajes de error con detalles internos, métricas engañosas y mayor superficie para entradas malformadas.

## 4. Solución Recomendada
```python
class CharacterContext(BaseModel):
    model_config = ConfigDict(extra="forbid")
    name: str = Field(min_length=1, max_length=80)
    bio: str | None = Field(default=None, max_length=2000)
    psychology: str | None = Field(default=None, max_length=2000)
    costume_notes: str | None = Field(default=None, max_length=1000)

class ManualDiff(BaseModel):
    stage: Literal["structured", "screenplay"]
    original_ai: str = Field(max_length=5000)
    user_override: str = Field(max_length=5000)

class UserLocks(BaseModel):
    pinned_terms: dict[constr(max_length=80), constr(max_length=80)] = {}
    manual_diffs: list[ManualDiff] = Field(default_factory=list, max_length=200)
    locked_fields: list[Literal["structured", "screenplay", "shots"]] = []
```
- Hacer que las `execute_*` reciban los modelos validados; capturar excepciones concretas, `logger.exception(...)` y devolver un `error_code` estable sin texto interno.
- Definir `class LlmClient(Protocol)` para `llm_client`.
- Tests de validación con `pytest.raises(ValidationError)`.
