# [ISSUE-011] `plot_hole_detector` no existe en `jg_plugin_filmmaker` ni en la spec: la auditoría de agujeros nunca llega al agente

- **Severidad:** Alta
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `app/Services/JgAgente/AgentClientService.php:224-232`, `jg_plugin_filmmaker/tools.py` (`get_filmmaker_tools()`, solo 3 tools), `docs/specs/03_jg_plugin_filmmaker_contract.md`, `docs/05_hmac_agent_integration.md` §3
- **Categoría:** Arquitectura

## 1. Descripción del Problema
`auditPlotHoles()` invoca `callTool('plot_hole_detector', ...)` → `POST /api/v1/tools/plot_hole_detector`. El plugin sólo registra `treatment_structuring`, `screenplay_formatter` y `shot_breakdown_detector` (`get_filmmaker_tools()`), y `grep plot_hole` en el plugin y en `docs/specs` no devuelve nada: **el contrato canónico no define la herramienta**. En consecuencia:

1. `jg_agente` responderá 404 → se traga silenciosamente (ISSUE-010) → se cae a `callDirectLlm()` (Groq directo) o al fallback determinista.
2. En el fallback determinista, `executeDeterministicFallback('plot_hole_detector')` produce una auditoría simulada: el usuario ve "agujeros detectados" que no vienen de ningún agente.
3. La funcionalidad "Detector de agujeros" (UI, `plot_hole`, `justified_plot_holes`) funciona por una ruta no documentada, no testeada contra el plugin y no versionada.

## 2. Evidencia de Código
```php
$result = $this->callTool('plot_hole_detector', $payload);   // tool inexistente en el plugin
```
```python
def get_filmmaker_tools() -> list[AgentTool]:
    return [treatment_structuring_tool, screenplay_formatter_tool, shot_breakdown_detector_tool]
```

## 3. Impacto Operativo o de Seguridad
Funcionalidad crítica sin contrato; fuga de guion a un LLM directo; resultados no reproducibles; deuda de acoplamiento oculto. Los tests nunca lo detectan porque el modo mock/Http::fake lo enmascara.

## 4. Solución Recomendada
- Añadir `plot_hole_detector` al plugin con `PlotHoleDetectorArgs` (Pydantic v2: `blocks: list[BlockPayload]`, `characters`, `locations`) y salida `{"detected_holes":[{"block_id","reason"}]}`; subir `version` y `tools_count` a 4.
- Documentarlo en `docs/specs/03_jg_plugin_filmmaker_contract.md` y `docs/05_hmac_agent_integration.md` (tabla de endpoints).
- Test de contrato en ambos repos (mismo fixture JSON) que falle si el cliente llama a un tool no registrado.
- Mientras tanto, la UI debe indicar explícitamente "auditoría local/simulada" cuando se use el fallback.
