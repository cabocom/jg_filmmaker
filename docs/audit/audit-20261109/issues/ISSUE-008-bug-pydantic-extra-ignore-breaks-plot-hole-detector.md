# [ISSUE-008] Bug Crítico en Esquema Pydantic: `ScriptBlockItem(extra="ignore")` Destruye `id` y `text` en `plot_hole_detector`

- **Severidad:** Crítica
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py:174-194, 266-287, 469-490` vs `jg_plugin_filmmaker/jg_plugin_filmmaker/plot_holes.py:70-79, 115-124`
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

