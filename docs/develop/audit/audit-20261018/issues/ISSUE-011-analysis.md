# Análisis Técnico: [ISSUE-011] `plot_hole_detector` no existe en `jg_plugin_filmmaker` ni en la spec: la auditoría de agujeros nunca llega al agente

- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Categoría:** Arquitectura / Ecosistema de Agentes
- **Vulnerabilidades / Errores:** CWE-1068 (Inconsistency Between Implementation and Documented Design), CWE-440 (Expected Behavior Violation)

---

## 1. Diagnóstico del Estado Previo

1. **Ausencia de Herramienta en el Catálogo Canónico:**
   - En `AgentClientService::auditPlotHoles()` (Laravel), el sistema realiza una llamada HTTP al endpoint de agentes: `callTool('plot_hole_detector', $payload)`.
   - Sin embargo, en `jg_plugin_filmmaker/tools.py`, `get_filmmaker_tools()` registraba únicamente 3 herramientas:
     1. `treatment_structuring`
     2. `screenplay_formatter`
     3. `shot_breakdown_detector`
   - La herramienta `plot_hole_detector` no existía en el plugin satélite Python.

2. **Consecuencias Operativas:**
   - Toda llamada real a `jg_agente` con `plot_hole_detector` devolvía un error 404 (herramienta no encontrada).
   - Debido al fallo silencioso (ISSUE-010), Laravel caía en bypass de LLM directo o en el fallback determinista local, enmascarando que la herramienta nunca se ejecutaba en el runtime cognitivo del agente satélite.
   - La interfaz de usuario mostraba auditorías de guión sin que el plugin las hubiera procesado.

3. **Discrepancia de Especificación:**
   - La documentación de contratos (`docs/specs/03_jg_plugin_filmmaker_contract.md`) y la telemetría del dashboard no contemplaban el cuarto contrato del pipeline cinematográfico.

---

## 2. Alternativas Evaluadas

1. **Eliminar la herramienta y mantener la detección exclusivamente dentro de Laravel:**
   - *Descartada:* La arquitectura del ecosistema `javiergalvez-ia` estipula que `jg_filmmaker` es una aplicación web desacoplada de la computación cognitiva pesada y el análisis semántico de IA, el cual debe residir en `jg_agente` y sus plugins satélites.
2. **Implementar formalmente `plot_hole_detector` en `jg_plugin_filmmaker` como 4.ª herramienta canónica (Opción Seleccionada):**
   - *Ventajas:*
     - Completa el catálogo oficial del plugin elevando a 4 las herramientas expuestas (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`, `plot_hole_detector`).
     - Define esquemas Pydantic v2 rigurosos (`PlotHoleDetectorArgs`) con tipado estricto de `blocks`, `characters` y `locations`.
     - Provee inferencia semántica con LLM mediante prompts especializados de análisis dramático y fallback determinista rule-based para CI/CD.
     - Contrato simétrico y testeable entre cliente PHP y proveedor Python.

---

## 3. Plan de Implementación

1. **Módulo de Detección en Python (`jg_plugin_filmmaker/plot_holes.py`):**
   - Implementar `detect_plot_holes(blocks, characters, locations, llm_client)`.
   - Formatear prompt del sistema `PLOT_HOLE_SYSTEM_PROMPT` para análisis de coherencia temporal, espacial y de personajes.
   - Soportar inferencia semántica con extracción JSON estructurada y fallback determinista.

2. **Definición de Herramienta (`jg_plugin_filmmaker/tools.py`):**
   - Definir `PlotHoleDetectorArgs` (Pydantic v2).
   - Definir `execute_plot_hole_detector(...) -> ToolResult`.
   - Definir y registrar `plot_hole_detector_tool` en `get_filmmaker_tools()`.

3. **Metadatos del Plugin (`jg_plugin_filmmaker/plugin.py`):**
   - Actualizar versión a 2.3.0 e incrementar a 4 herramientas registradas.

4. **Pruebas Automatizadas:**
   - Añadir tests unitarios en `tests/test_filmmaker_tools.py` comprobando la ejecución de `plot_hole_detector` tanto con agujeros simulados como con guión consistente.
   - Verificar integración en `jg_filmmaker` con `AgentClientService`.
