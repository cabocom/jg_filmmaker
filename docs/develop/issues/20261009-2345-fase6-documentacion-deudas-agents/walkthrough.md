# Walkthrough — Fase 6: Documentación Exhaustiva, Deudas Técnicas y Protocolo Pre-Commit en AGENTS.md

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker` & Ecosistema `javiergalvez-ia`  
**Autor:** Antigravity (Tech Lead, Arquitecto de Software)

---

## 1. Prompt Recibido

```text
[PROJECT: jg_filmmaker & Ecosistema jg_ia] - Fase 6: Documentación Exhaustiva, Deudas Técnicas y Protocolo Pre-Commit en AGENTS.md

Actúa como Tech Lead, Arquitecto de Software y Auditor Técnico Senior del ecosistema jg_ia.

Hemos completado el desarrollo técnico de las 5 fases de la plataforma cinematográfica `jg_filmmaker` y de su plugin satélite `jg_plugin_filmmaker`. Ahora debemos generar una documentación técnica exhaustiva y viva del sistema, de modo que cualquier IA o ingeniero que lea estos documentos en el futuro comprenda cada detalle de la arquitectura, sus flujos, sus deudas técnicas y su estado operativo real sin tener que interpretar el código a ciegas.

Además, debes actualizar las directrices de trabajo permanente en `AGENTS.md` para blindar el flujo de commits en todo el ecosistema.

### TAREA 1: REGLAS PERMANENTES EN `AGENTS.md` (Workspace raíz `javiergalvez-ia`)
Actualiza el archivo `AGENTS.md` de la raíz del workspace para incorporar dos reglas de oro obligatorias para cualquier IA o desarrollador:
1. Protocolo de "Foto Real" y Actualización de Documentación Pre-Commit.
2. Validación CI/CD Pre-Commit Ineludible.
Mantener explícita la regla preexistente: PROHIBIDO ejecutar git push automáticamente.

### TAREA 2: DOCUMENTACIÓN EXHAUSTIVA DE `jg_filmmaker` (`jg_filmmaker/PROJECT_CONTEXT.md` y `README.md`)
Redacta un jg_filmmaker/PROJECT_CONTEXT.md minucioso, profundo y sin omisiones con las secciones obligatorias (Visión, 6 etapas, user_locks, Biblia drawer Cmd+B, arquitectura/seguridad UUIDv4/HMAC, catálogo archivos/rutas/tests y estado real / deudas técnicas).

### TAREA 3: DOCUMENTACIÓN DE `jg_plugin_filmmaker` (`jg_plugin_filmmaker/PROJECT_CONTEXT.md` y `README.md`)
Actualizar documentando las 3 herramientas, esquemas Pydantic v2, RiskLevel.LOW, 10 tests Pytest, jg link y deudas técnicas.

### TAREA 4: ACTUALIZACIÓN DEL WORKSPACE RAÍZ (`javiergalvez-ia`)
Actualizar PROJECT_CONTEXT.md, README.md y ECOSYSTEM_FULL_SPEC.md en la raíz para incorporar formalmente jg_filmmaker y jg_plugin_filmmaker.
```

---

## 2. Acciones Realizadas

### 2.1. Reglas en `AGENTS.md` (Raíz)
- Se actualizaron las directrices de la Sección 1 ("Operaciones Git, Calidad y Despliegue") estableciendo como obligatorias:
  1. La prohibición absoluta de `git push` autónomo.
  2. El protocolo de **"Foto Real" y Actualización de Documentación Pre-Commit**.
  3. La **Validación CI/CD Pre-Commit Ineludible** (`scripts/check.sh --cicd`, linters y tests unitarios sin fallos).

### 2.2. Documentación Exhaustiva y Modular de `jg_filmmaker` (`docs/`)
- Se creó la estructura canónica de documentación arquitectónica modular en `docs/` (en paridad con `jg_dashboard` y `jg_agente`):
  - [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/README.md): Índice y catálogo de navegación.
  - [01_architecture.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/01_architecture.md): Arquitectura general, C4, stack Laravel 12 y seguridad anti-IDOR (UUIDv4).
  - [02_pipeline_stages.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/02_pipeline_stages.md): Detalle técnico de las 6 etapas del pipeline.
  - [03_user_locks_governance.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/03_user_locks_governance.md): Estructura JSONB y algoritmo de resolución de candados y `pinned_terms`.
  - [04_context_bible.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/04_context_bible.md): Biblia contextual y cajón lateral `ContextBibleDrawer` (`Cmd+B`).
  - [05_hmac_agent_integration.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/05_hmac_agent_integration.md): Protocolo de firma HMAC-SHA256 y fallbacks locales.
  - [06_media_takes_ffmpeg.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/06_media_takes_ffmpeg.md): Carga física particionada por UUID y servicio de ensamble continuo FFmpeg.
  - [07_testing_quality_cicd.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/07_testing_quality_cicd.md): Suite de 28 tests PHPUnit, Pint y catálogo de deudas técnicas.
- Se mantuvieron actualizados [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/PROJECT_CONTEXT.md) y [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/README.md).

### 2.3. Documentación Exhaustiva y Modular de `jg_plugin_filmmaker` (`docs/`)
- Se creó la estructura de documentación arquitectónica en `docs/`:
  - [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docs/README.md): Índice del plugin.
  - [01_plugin_architecture.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docs/01_plugin_architecture.md): Aislamiento y contratos con `jg_sdk.contracts`.
  - [02_tools_specification.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docs/02_tools_specification.md): Especificación de las 3 herramientas y esquemas Pydantic v2.
  - [03_user_locks_resolution.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docs/03_user_locks_resolution.md): Algoritmo de preservación de `pinned_terms` y campos congelados.
  - [04_testing_quality_cicd.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/docs/04_testing_quality_cicd.md): 10 tests Pytest, Ruff linter y comando `jg link`.
- Se mantuvieron actualizados [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/PROJECT_CONTEXT.md) y [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/README.md).

### 2.4. Topología y Especificación en el Workspace Raíz
- [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/README.md): Añadidos `jg_filmmaker` (puerto 8010) y `jg_plugin_filmmaker` al árbol de subproyectos, tabla de puertos y listado de repositorios.
- [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/PROJECT_CONTEXT.md): Incorporada la suite y el plugin en los pilares arquitectónicos, matriz de evaluación y enlaces documentales.
- [ECOSYSTEM_FULL_SPEC.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/ECOSYSTEM_FULL_SPEC.md): Creada la Sección 6 completa con especificación exhaustiva de ambas piezas y su integración en `scripts/check.sh`.

---

## 3. Verificación de Calidad

- **Linters y Formato:**
  - `vendor/bin/pint --test` en `jg_filmmaker`: 100% aprobado.
  - `ruff check .` en `jg_plugin_filmmaker`: 100% aprobado.
- **Pruebas Unitarias:**
  - `php artisan test` en `jg_filmmaker`: 28 tests pasados (144 aserciones).
  - `pytest` en `jg_plugin_filmmaker`: 10 tests pasados.
- **Pipeline CI/CD Unificado:**
  - `bash scripts/check.sh --cicd`: 100% aprobado.

