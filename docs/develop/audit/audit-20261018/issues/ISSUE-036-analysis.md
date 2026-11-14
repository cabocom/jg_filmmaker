# Análisis - ISSUE-036: Pydantic v2 Strict Typing y Robustez de Modelos en jg_plugin_filmmaker

## 1. Situación Inicial
- En `jg_plugin_filmmaker/tools.py`, los modelos de entrada (`TreatmentStructuringArgs`, `ScreenplayFormatterArgs`, `PlotHoleDetectorArgs`) declaran:
  - `characters_context: list[dict[str, Any]] | None`
  - `location_context: dict[str, Any] | None`
  - `user_locks: dict[str, Any] | None`
  - `blocks: list[dict[str, Any]]`
- No existen submodelos Pydantic para validar la estructura interna de estos diccionarios. Si Laravel o un cliente envía un tipo incorrecto (por ejemplo `pinned_terms` como entero, o `characters_context` con elementos sin `name`), el error explota en tiempo de ejecución (`TypeError`, `AttributeError`) dentro de funciones auxiliares.
- Los bloques `try/except Exception as e:` capturan genéricamente cualquier excepción y concatenan `str(e)` en la respuesta pública de `ToolResult(error=...)`, lo cual puede filtrar rutas de archivos, clases internas o stack traces, sin utilizar `logger.exception(...)` para trazabilidad forense.
- La variable `llm_client` está anotada como `Any`, sin un contrato o `Protocol` explícito.

## 2. Solución Propuesta

### A. Submodelos Pydantic Tipados con `ConfigDict(extra="ignore")`
Se deben crear submodelos para tipar las entidades del dominio:
1. `CharacterContext`:
   - `name: str = Field(min_length=1, max_length=120)`
   - `bio: str | None = Field(default=None, max_length=5000)`
   - `psychology: str | None = Field(default=None, max_length=5000)`
   - `costume_notes: str | None = Field(default=None, max_length=2000)`
   - Configuración: `model_config = ConfigDict(extra="ignore")` para tolerar metadatos adicionales enviados por Eloquent (`id`, `uuid`, timestamps).
2. `LocationContext`:
   - `name: str = Field(min_length=1, max_length=120)`
   - `description: str | None = Field(default=None, max_length=5000)`
   - `lighting_notes: str | None = Field(default=None, max_length=2000)`
   - `sound_notes: str | None = Field(default=None, max_length=2000)`
   - `model_config = ConfigDict(extra="ignore")`
3. `ManualDiffItem`:
   - `stage: str = Field(default="structured", max_length=50)`
   - `original_ai: str = Field(default="", max_length=10000)`
   - `user_override: str = Field(default="", max_length=10000)`
   - `created_at: str | None = None`
   - `model_config = ConfigDict(extra="ignore")`
4. `UserLocksModel`:
   - `pinned_terms: dict[str, str] = Field(default_factory=dict)`
   - `manual_diffs: list[ManualDiffItem] = Field(default_factory=list, max_length=200)`
   - `archived_diffs: list[dict[str, Any]] = Field(default_factory=list, max_length=500)`
   - `locked_fields: list[str] = Field(default_factory=list, max_length=50)`
   - `model_config = ConfigDict(extra="ignore")`
5. `ScriptBlockInput`:
   - `type: str = Field(default="action", max_length=50)`
   - `content: str = Field(default="", max_length=10000)`
   - `character: str | None = Field(default=None, max_length=120)`
   - `model_config = ConfigDict(extra="ignore")`

### B. Protocolo para LLM Client
- Definir `class LLMClientProtocol(Protocol):` con el método `async def generate(...)` o `def generate(...)` correspondiente para eliminar anotaciones `Any` descontroladas.

### C. Manejo Seguro de Excepciones y Logging Forense
- Sustituir `str(e)` crudo devuelto al cliente por mensajes de error limpios y códigos de error estables, emitiendo `logger.exception("Fallo en ejecución de tool ...")` para retener la traza técnica en logs del servidor sin exponerla en la respuesta JSON.

### D. Conteo Real de Candados Aplicados
- En `execute_treatment_structuring` y `execute_screenplay_formatter`, contabilizar cuántos candados fueron efectivamente aplicados sobre el texto, distinguiendo candados declarados de candados activos.

## 3. Plan de Verificación
- Escribir `tests/test_pydantic_strict_typing.py` para verificar:
  - Validación correcta de `CharacterContext`, `LocationContext`, `UserLocksModel`.
  - Rechazo de tipos inválidos en `pinned_terms` o campos no conformes.
  - Comportamiento de tolerancia `extra="ignore"` ante metadatos Eloquent.
  - Logging estructurado ante excepciones.
- Validar linters (`ruff check .`, `ruff format .`) y pasar suite completa de pytest.
