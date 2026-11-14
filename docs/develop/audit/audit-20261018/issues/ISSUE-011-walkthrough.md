# Walkthrough: [ISSUE-011] Incorporación de `plot_hole_detector` en `jg_plugin_filmmaker` y formalización del contrato

- **Componente:** `jg_plugin_filmmaker` / `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se ha cerrado la brecha arquitectónica donde el cliente web `jg_filmmaker` intentaba consumir la herramienta `plot_hole_detector` pero el plugin Python `jg_plugin_filmmaker` solo exponía 3 herramientas:

1. **Nuevo Motor de Análisis en Python (`jg_plugin_filmmaker/plot_holes.py`):**
   - Se implementó `detect_plot_holes(blocks, characters, locations, llm_client)`.
   - Incorpora el prompt del sistema `PLOT_HOLE_SYSTEM_PROMPT` para análisis semántico profundo con LLM, exigiendo salida JSON estructurada `{"detected_holes": [{"block_id", "reason"}]}`.
   - Implementa un motor de reglas determinista de fallback para entornos sin tokens o de CI/CD, detectando saltos espaciales inexplicables y contradicciones de estado de personajes.

2. **Esquema Pydantic v2 y Descriptores de Herramienta (`tools.py`):**
   - Se creó `PlotHoleDetectorArgs` tipando estrictamente `blocks: list[dict[str, Any]]`, `characters` y `locations`.
   - Se implementó `execute_plot_hole_detector(...) -> ToolResult`.
   - Se registró `plot_hole_detector_tool` en `get_filmmaker_tools()`, elevando a 4 las herramientas canónicas del plugin.

3. **Metadatos y Salud del Plugin (`plugin.py`):**
   - Actualización a la versión `2.3.0` y reporte de `tools_count = 4`.

4. **Pruebas Automatizadas:**
   - 15 tests unitarios en `jg_plugin_filmmaker` (Pytest + Ruff) verificando el registro, ejecución y detección de inconsistencias.

---

## 2. Modificaciones en el Código

### `jg_plugin_filmmaker/jg_plugin_filmmaker/tools.py`
```python
class PlotHoleDetectorArgs(BaseModel):
    blocks: list[dict[str, Any]] = Field(..., description="Lista de bloques narrativos ordenados del guión a auditar.")
    characters: list[dict[str, Any]] | None = Field(default=None, description="Contexto opcional de personajes.")
    locations: list[dict[str, Any]] | None = Field(default=None, description="Contexto opcional de localizaciones.")

def execute_plot_hole_detector(blocks, characters=None, locations=None, llm_client=None) -> ToolResult:
    ...
    return ToolResult(
        success=True,
        output=f"Auditoría dramática completada: {len(holes)} agujero(s) detectado(s).",
        metadata={"stage": "plot_holes", "detected_holes": holes, "total_holes": len(holes)},
    )
```

---

## 3. Pruebas Automatizadas

Se añadieron pruebas en `tests/test_filmmaker_tools.py` y `tests/test_plugin.py`:
- `test_plot_hole_detector_detects_inconsistencies`: verifica la detección de anomalías espaciales y contradicciones.
- `test_plot_hole_detector_consistent_script_returns_zero_holes`: valida que guiones consistentes reportan 0 agujeros.
- `test_plugin_runner_integration`: verifica la ejecución en el runner oficial de agentes.

Resultado: 15/15 tests pasando en `jg_plugin_filmmaker` con Ruff 100% limpio.
