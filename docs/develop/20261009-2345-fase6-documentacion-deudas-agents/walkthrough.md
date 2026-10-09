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

### 2.2. Documentación Exhaustiva de `jg_filmmaker`
- Se redactó [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/PROJECT_CONTEXT.md) v2.0 detallando:
  - Visión general del desacoplamiento.
  - Pipeline de 6 etapas: Bruto, Tratamiento, Guión Literario (Fountain), Planos Técnicos, Rodaje/Tomas (particionado físico UUID) y Montaje NLE (timeline con ensamble FFmpeg).
  - Algoritmo de preservación de candados `user_locks` (`locked_fields`, `manual_diffs`, `pinned_terms`).
  - Cajón lateral `ContextBibleDrawer` (`Cmd+B`).
  - Seguridad UUIDv4 y firma criptográfica HMAC-SHA256 con fallback determinista.
  - Catálogo exhaustivo de archivos, modelos, servicios, rutas web y suite de 28 tests PHPUnit pasando al 100%.
  - Registro de deudas técnicas: autenticación demo, transcodificación heterogénea FFmpeg, exportadores FCPXML/EDL y almacenamiento S3.
- Se actualizó [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/README.md).

### 2.3. Documentación Exhaustiva de `jg_plugin_filmmaker`
- Se redactó [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/PROJECT_CONTEXT.md) v2.0 cubriendo las 3 herramientas (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`), los esquemas de entrada Pydantic v2, el nivel de riesgo `RiskLevel.LOW`, los 10 tests Pytest, el comando `jg link` y las deudas técnicas de análisis visual.
- Se actualizó [README.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/README.md).

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
