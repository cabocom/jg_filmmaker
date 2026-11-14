# [ISSUE-008] Bug Crítico en Esquema Pydantic: `ScriptBlockItem(extra="ignore")` Destruye `id` y `text` en `plot_hole_detector`

- **Severidad:** Crítica
- **Estado:** Resuelto (Cerrado)
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/jg_plugin_filmmaker/schemas/context.py:131-155`, `jg_plugin_filmmaker/jg_plugin_filmmaker/schemas/tools.py:80-92`, `jg_plugin_filmmaker/jg_plugin_filmmaker/plot_holes.py:69-125`
- **Categoría:** Calidad

## 1. Descripción
En `jg_plugin_filmmaker/tools.py`, el esquema de entrada `PlotHoleDetectorArgs` declara `blocks: list[ScriptBlockItem] | list[dict[str, Any]]`. Debido al orden de evaluación de uniones en Pydantic v2, cuando se pasa una lista de diccionarios (como los enviados por `jg_filmmaker` en `ScriptEditor::detectPlotHoles()` o en `plot_hole_detector_tool.run(...)`), Pydantic valida cada elemento contra el primer tipo de la unión: `ScriptBlockItem`.

Sin embargo, `ScriptBlockItem` está diseñado para bloques de salida de Fountain (`type`, `content`, `character`) y tiene configurado `model_config = ConfigDict(extra="ignore")`. No define los campos `id`, `text`, `raw_text`, `structured_text` ni `screenplay_content` que envía `jg_filmmaker` y que espera `detect_plot_holes()` en `plot_holes.py`.

Como resultado:
1. Pydantic convierte cada diccionario `{"id": "uuid-1", "text": "MARCUS entra...", "raw_text": "..."}` en una instancia `ScriptBlockItem(type="action", content="", character=None)`, **eliminando silenciosamente `id`, `text`, `raw_text`, `structured_text` y `screenplay_content`**.
2. En `execute_plot_hole_detector()`, `b.model_dump()` produce `{"type": "action", "content": "", "character": None}`.
3. `detect_plot_holes()` busca `b.get("id")` y `b.get("text") or b.get("screenplay_content") or b.get("structured_text") or b.get("raw_text")`, obteniendo siempre cadenas vacías `""` y **devolviendo 0 agujeros de guion (`issues: []`) en el 100% de las invocaciones validadas por `AgentTool.run()`**.

## 2. Evidencia de Código
En [`jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py#L174-L194):
```python
class ScriptBlockItem(BaseModel):
    """Representa un bloque de guion individual para validación o análisis."""

    model_config = ConfigDict(extra="ignore")

    type: str = Field(
        default="action",
        description="Tipo de bloque (scene_heading, action, character, dialogue, etc.)",
    )
    content: str = Field(default="", description="Contenido textual del bloque")
    character: str | None = Field(
        default=None, description="Nombre del personaje asociado si aplica"
    )
```

Y en [`jg_plugin_filmmaker/jg_plugin_filmmaker/plot_holes.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/plot_holes.py#L115-L125):
```python
    for idx, b in enumerate(blocks):
        b_id = str(b.get("id") or f"block_{idx + 1}")
        b_text = str(
            b.get("text")
            or b.get("screenplay_content")
            or b.get("structured_text")
            or b.get("raw_text")
            or ""
        )
```

En [`jg_plugin_filmmaker/tests/test_filmmaker_tools.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/tests/test_filmmaker_tools.py#L209-L214), el test unitario solo verificaba `assert res4.success is True`, ocultando que `res4.output["issues"]` quedaba vacío a pesar de que el bloque tenía un error evidente de vestuario ("entra con smoking negro" vs "camisa hawaiana roja").

## 3. Impacto Operativo o Riesgo
- **Inutilización Funcional de `plot_hole_detector`:** Cualquier invocación a `plot_hole_detector` que atraviese la validación de esquemas Pydantic de `AgentTool.run()` (o el validador de herramientas de `jg_agente`) pierde todo el texto y los UUIDs de los bloques, devolviendo siempre "0 inconsistencias detectadas" (falso negativo total).

## 4. Solución o Mejora Recomendada
Definir un modelo Pydantic específico `PlotHoleBlockInput` con todos los campos del contrato real (`id`, `text`, `raw_text`, `structured_text`, `screenplay_content`, `content`, `type`, `character`) y añadir aserciones sobre `res4.output["issues"]` en `test_filmmaker_tools.py`:

```python
class PlotHoleBlockInput(BaseModel):
    """Bloque de entrada para detección de agujeros de guion y continuidad."""

    model_config = ConfigDict(extra="allow")

    id: str | None = Field(default=None, description="UUID o identificador del bloque")
    text: str | None = Field(default=None, description="Texto consolidado del bloque")
    raw_text: str | None = Field(default=None, description="Texto bruto original")
    structured_text: str | None = Field(default=None, description="Tratamiento estructurado")
    screenplay_content: str | None = Field(default=None, description="Contenido en formato guion")
    content: str | None = Field(default=None, description="Contenido alternativo")
```

## 5. Estado y Resolución Técnica (Noviembre 2026)

- **Corrección de Esquemas Pydantic v2:**
  - En `jg_plugin_filmmaker/schemas/context.py`, se implementó `PlotHoleBlockInput` y se dotó a `ScriptBlockItem` de todos los campos que componen el contrato de bloques (`id`, `text`, `raw_text`, `structured_text`, `screenplay_content`, `content`, `type`, `character`) configurando `model_config = ConfigDict(extra="allow")`.
  - En `jg_plugin_filmmaker/schemas/tools.py`, se actualizó `PlotHoleDetectorArgs` para utilizar `blocks: list[PlotHoleBlockInput]`, evitando que `tool_executor` en `jg_agente` o `AgentTool.run()` eliminen los UUIDs y los contenidos textuales al deserializar el payload.
  - En `jg_plugin_filmmaker/plot_holes.py`, se amplió la resolución de texto de cada bloque para consultar `b.get("content")` como fallback adicional y soportar la asignación resiliente de `f"block_{idx + 1}"` cuando el ID del bloque no esté presente.
- **Suite de Pruebas y Paridad de Validación:**
  - Se actualizó `tests/test_filmmaker_tools.py` con una prueba de integración específica (`test_plot_hole_detector_pydantic_schema_preserves_id_and_text`) que simula la validación completa de esquemas en `tool_executor` y comprueba que se detecten las inconsistencias dramáticas asociadas al `block_id` real.
  - Suite de 63 tests pasando al 100% y linters Ruff (formato y reglas) sin incidencias.
- **GitLab Issue:** [jg_filmmaker#13](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/13) cerrado.


