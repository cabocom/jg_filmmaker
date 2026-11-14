# Análisis Técnico — Tema Blanco/Claro y Corrección de Heurística en Pipeline de Guión

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker` / Ecosistema `javiergalvez-ia`  
**Autor:** Antigravity (Ingeniero Senior Full-Stack y Arquitecto de Software)

---

## 1. Contexto y Requerimientos del Usuario

El usuario reportó tres cuestiones críticas:
1. **Soporte de Tema Blanco (Light Mode):** Homogeneizar la experiencia visual de `jg_filmmaker` con `jg_hub` y `jg_dashboard`, incorporando tema claro por defecto con selector dinámico claro/oscuro (☀️/🌙) y persistencia en `localStorage`.
2. **Anomalías en la Transformación de Etapas (Tratamiento y Guión):** Al añadir dos párrafos narrativos nuevos en el Bruto ("Javier mira a Juan con miedo y le dice que ya están llegando." y "Juan le pregunta qué quien está llegando."), el sistema introdujo inconsistencias notables:
   - Cabecera genérica (`INT. LOCALIZACION - DIA`) en lugar del slugline real de la escena (`INT. BAR CENTRAL - NOCHE 1`).
   - Repetición de prefijo de tratamiento en la primera acción (`En BAR CENTRAL (INT): ...`).
   - Transformación errónea de una acción narrativa de Javier en diálogo teatral (`JAVIER \n mira a Juan con miedo...`).
   - Omisión de formato de diálogo para Juan.
3. **Estado Real de la Inteligencia Artificial:** Esclarecer si el sistema está ejecutando inferencia real de Modelos de Lenguaje (LLM) o heurísticas deterministas.

---

## 2. Diagnóstico de Causa Raíz

### 2.1. Causa de las Anomalías en el Pipeline de Guión
1. **Pérdida de contexto de localización:** `ScriptPipelineService::transitionToScreenplay` invocaba `$this->agentClient->formatScreenplay($treatmentText, $activeLocks)` sin pasar la localización de la escena. El cliente utilizaba `INT. LOCALIZACION - DIA` por defecto.
2. **Duplicación de prefijos:** El formateador determinista no sanitizaba las etiquetas `En LOCALIZACIÓN:` o `LOCALIZACIÓN (INT/EXT):` generadas en la fase de tratamiento, arrastrándolas al cuerpo de acción literaria.
3. **Falso positivo en detección de diálogo:** El regex heurístico anterior (`/^Javier:?\s*(.*)$/i`) consideraba los dos puntos como opcionales (`:?`). Dado que la frase comenzaba con el nombre del personaje (`Javier mira a Juan...`), el parser clasificaba erróneamente la descripción de la acción como un parlamento.
4. **Ausencia de Juan en la Biblia:** El personaje Juan no estaba dado de alta en la base de datos de personajes ni el texto contenía signos de diálogo (`:` o comillas). Al no haber comprensión semántica, el algoritmo no pudo inferir que Juan intervenía dialogando.

### 2.2. Estado de la IA en el Ecosistema
- Actualmente **NO se está ejecutando un LLM en tiempo real** en este paso.
- La infraestructura de seguridad HMAC-SHA256, los contratos y el plugin `jg_plugin_filmmaker` están implementados y probados, pero `jg_agente` opera en modo de fallbacks heurísticos locales y deterministas hasta que se configure el endpoint LLM en el grafo de LangGraph.

---

## 3. Decisiones de Arquitectura e Implementación

### 3.1. Tema Blanco y Persistencia (`localStorage`)
- **Compatibilidad Tailwind v4:** Se incorporó `@custom-variant dark (&:where(.dark, .dark *));` en `resources/css/app.css` para permitir el toggle mediante clase `.dark` en el elemento `<html>`.
- **Detección temprana en `<head>`:** Se añadió un script inline que consulta `localStorage.getItem('jg_theme') || localStorage.getItem('console_theme')` y aplica o remueve la clase `.dark` sin causar parpadeo visual (FOUC).
- **Selector UI:** Se integró un botón toggle (☀️/🌙) en el navbar principal.
- **Refactorización completa de vistas:** Se adaptaron `layouts/app.blade.php`, `project-list.blade.php`, `script-editor.blade.php`, `rough-cut-timeline.blade.php` y `context-bible-drawer.blade.php` con paletas neutras de alta legibilidad (`bg-slate-50`, `bg-white`, `text-slate-900`, `border-slate-200` y sus contrapartes `dark:`).

### 3.2. Robustecimiento del Formateador de Guión
- Se inyectó `$scene->location` en `transitionToScreenplay`.
- Se implementó limpieza de prefijos `En [LUGAR]:` al pasar a guión.
- Se exigieron dos puntos explícitos (`:`) o estructuras de cita con comillas (`dice: "..."`) para transformar párrafos en bloques de diálogo Fountain (`PERSONAJE\n(acotación)\ndiálogo`), evitando falsos positivos cuando el nombre del personaje es el sujeto gramatical de una frase de acción.
