# Feature: Sistema Híbrido de Inteligencia de Personajes (Detección Semántica con IA + Grounding Determinista y Auditoría con Forzado HITL)

**Fecha:** 2026-11-08  
**Proyecto:** `jg_filmmaker` & `jg_plugin_filmmaker`  
**Autor:** Javier Gálvez / Antigravity  
**Estado:** En Implementación  

---

## 1. Contexto y Objetivos

En el flujo de trabajo cinematográfico de `jg_filmmaker`, la gestión de personajes en la Biblia de Producción presentaba dos problemas estructurales derivados del uso de expresiones regulares aisladas (`preg_match`):

1. **Falsos positivos semánticos:** Acotaciones narrativas y descripciones de acción comunes (ej. *"Edurne, asustada, mira a su alrededor"* o *"Sonido en off de agua salpicando"*) eran capturadas como personajes en la base de datos si coincidían con mayúsculas o dos puntos, generando personajes espurios como *"Mira A Su Alrededor"* o *"Sonido En Off"*.
2. **Bloqueo sin escape en la auditoría de borrado:** La comprobación de uso para eliminar un personaje (`ContextBibleDrawer::auditCharacterUsages`) buscaba la cadena del nombre en cualquier bloque de texto de la película. Si la frase verbal seguía en la prosa de acción, la aplicación bloqueaba el borrado con un callejón sin salida (*"No se puede eliminar el personaje... está siendo utilizado actualmente"*), impidiendo al cineasta purgar elementos fantasma.
3. **Necesidad de personajes mudos/silentes:** Un guion cinematográfico contiene personajes legítimos que no tienen líneas de diálogo (ej. vigilantes, transeúntes, figuras físicas que intervienen en la acción). Por tanto, la detección no puede limitarse únicamente a diálogos hablados.

El objetivo de esta funcionalidad es implementar un **Sistema Híbrido de Inteligencia de Personajes** que combina el poder semántico de la IA (LLM) con una red determinista de seguridad (grounding textual, stopwords técnicas y matriz de consenso), garantizando al mismo tiempo la **soberanía total del director (HITL)** para forzar la eliminación de cualquier entidad.

---

## 2. Arquitectura de la Solución

```text
+-----------------------------------------------------------------------------------+
|                        PIPELINE HÍBRIDO DE PERSONAJES                             |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [Texto de Guion / Bloque Dramático]                                              |
|             │                                                                     |
|             ├───► [IA Semántica en jg_plugin_filmmaker]                           |
|             │     - Analiza sujetos dramáticos activos (parlantes y mudos)        |
|             │     - Discierne verbos/acotaciones de personas reales               |
|             │     - Genera JSON estructurado con nombres, roles y evidencia       |
|             │                                                                     |
|             ├───► [Anclas Deterministas Fuertes]                                  |
|             │     - Encabezados canónicos Fountain / Diálogo                      |
|             │     - Roles con artículo explícito (el médico, la inspectora...)    |
|             │                                                                     |
|             ▼                                                                     |
|  [Motor de Consenso y Grounding Textual]                                          |
|  1. Verificación de presencia física: ¿Existe el nombre en el texto? (Anti-alucina)|
|  2. Filtro estricto de Stopwords cinematográficas (SONIDO EN OFF, CORTE, etc.)    |
|  3. Matriz de decisión:                                                           |
|     - Match IA + Determinismo: Alta confianza -> Aprobado                        |
|     - Solo IA con evidencia textual: Personaje silente/mudo -> Aprobado           |
|     - Fallo de API / Discrepancia: Fallback determinista seguro sin caída         |
|             │                                                                     |
|             ▼                                                                     |
|  [Biblia de Producción (characters) & Auditoría de Uso HITL]                      |
|  - Auditoría IA para distinguir mención como personaje vs coincidencia léxica     |
|  - Botón "Eliminar de todos modos" (Forzado soberano del director)                |
+-----------------------------------------------------------------------------------+
```

---

## 3. Desglose en 3 Fases de Implementación

### Fase 1: Motor Cognitivo en `jg_plugin_filmmaker`
- Creación del módulo `characters.py` con dos herramientas canónicas:
  - `extract_characters`: Extrae personajes reales con su rol, descripción y si son hablantes o mudos, descartando acotaciones y efectos técnicos.
  - `audit_character_usage`: Analiza si un personaje interviene realmente como sujeto dramático en una escena o si su nombre es solo una coincidencia léxica en una acotación.
- Capa determinista de grounding textual y catálogo de stopwords técnicas de cine.
- Exposición de tools en `tools.py` y `plugin.py`.
- Suite exhaustiva de tests en `pytest` cubriendo personajes mudos, falsos positivos y modo mock offline determinista.

### Fase 2: Integración en Plataforma Web `jg_filmmaker`
- Conexión cliente en `AgentClientService.php` (`extractCharacters` y `auditCharacterUsage`).
- Sincronización en `ScriptPipelineService.php` mediante la matriz de consenso.
- Actualización de `ContextBibleDrawer.php` para delegar la comprobación en la auditoría semántica.
- Actualización de la vista Blade `context-bible-drawer.blade.php`: incorporación del botón **"Eliminar de todos modos"** con confirmación modal destacada.
- Suite de tests en PHPUnit asegurando 100% de cobertura y paso de Laravel Pint.

### Fase 3: Verificación y Cierre
- Validación en base de datos con el guion real del proyecto.
- Purga exitosa de personajes espurios (*"Mira A Su Alrededor"*, *"Sonido En Off"*).
- Documentación contextual viva en `PROJECT_CONTEXT.md` de ambos repositorios.
- Cierre del issue en GitLab con comentario técnico detallado.

