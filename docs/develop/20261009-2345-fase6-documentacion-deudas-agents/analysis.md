# Análisis Técnico — Fase 6: Documentación Exhaustiva, Deudas Técnicas y Protocolo Pre-Commit en AGENTS.md

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker` & Ecosistema `javiergalvez-ia`  
**Autor:** Antigravity (Tech Lead, Arquitecto de Software y Auditor Técnico)

---

## 1. Contexto y Objetivos de la Fase 6

Tras haber implementado con éxito las 5 fases funcionales de la suite cinematográfica `jg_filmmaker` y de su plugin satélite `jg_plugin_filmmaker`:
1. **Fase 1:** Arquitectura de datos con UUIDv4, migraciones y modelos Eloquent sin IDOR.
2. **Fase 2:** Interfaz reactiva Livewire 3/Tailwind, editor de 3 columnas y cajón lateral `ContextBibleDrawer` (`Cmd+B`).
3. **Fase 3A:** Plugin canónico Python `jg_plugin_filmmaker` con contratos `jg_agente_sdk` y 10 tests Pytest.
4. **Fase 3B:** Cliente HTTP con firma criptográfica HMAC-SHA256, servicio de resolución de bloqueos `UserLockResolver` y reactividad en editor.
5. **Fase 4:** Rodaje, carga de archivos de vídeo por toma con almacenamiento físico por UUID y timeline NLE `RoughCutTimeline`.
6. **Fase 5:** Orquestador de renderizado con FFmpeg (`RoughCutAssemblyService`) e integración en `scripts/check.sh`.
7. **Evolución Intermedia:** Tema Blanco (Light Mode) con selector interactivo y corrección heurística de diálogos/sluglines.

La **Fase 6** tiene por objetivo blindar el ecosistema mediante:
- **Reglas de Oro en `AGENTS.md`:** Protocolo ineludible de "Foto Real" técnica y actualización documental pre-commit, junto con validación obligatoria de CI/CD antes de cualquier consolidación de código (manteniendo la prohibición estricta de `git push` automático).
- **Documentación Técnica Exhaustiva y Sin Omisiones:** Redacción minuciosa de `PROJECT_CONTEXT.md` y `README.md` en `jg_filmmaker` y `jg_plugin_filmmaker`.
- **Actualización de la Topología Global:** Incorporación formal de `jg_filmmaker` y `jg_plugin_filmmaker` en `PROJECT_CONTEXT.md`, `README.md` y `ECOSYSTEM_FULL_SPEC.md` del workspace raíz.
- **Catálogo de Deudas Técnicas Conocidas:** Documentar de forma transparente las limitaciones actuales (autenticación demo, transcodificación heterogénea en FFmpeg, exportadores NLE FCPXML/EDL y almacenamiento S3) para futuras iteraciones de ingeniería.

---

## 2. Decisiones de Arquitectura y Normativa

### 2.1. Protocolo de "Foto Real" Pre-Commit (`AGENTS.md`)
Se estipula como norma inmutable que ningún asistente de IA o ingeniero humano podrá realizar `git commit` sin haber registrado la foto real del código modificado en la documentación técnica viva (`PROJECT_CONTEXT.md`, `README.md`, specs o `docs/develop/`). Esto elimina la divergencia documental histórica (*drift*) y garantiza que cualquier agente futuro entienda la arquitectura sin conjeturas.

### 2.2. Validación CI/CD Ineludible Pre-Commit (`AGENTS.md`)
Se formaliza que ningún commit se consolidará si existen fallos en la batería de tests o violaciones de estilo/linters:
- `bash scripts/check.sh --cicd` a nivel global del ecosistema.
- `vendor/bin/pint --test` y `php artisan test` en componentes Laravel.
- `ruff check .` y `pytest` en componentes Python.

### 2.3. Transparencia en Deudas Técnicas
En lugar de ocultar las decisiones de diseño temporales, se documentan explícitamente como deudas técnicas asumidas:
- **Middleware `EnsureDemoUserAuthenticated`:** Facilita el desarrollo local ágil, pero requiere converger con el sistema de autenticación central de `jg_hub` y pasarela de cuotas de pago.
- **FFmpeg Concat Demuxer:** Óptimo en velocidad para clips idénticos, pero requiere transcodificación intermedia ante clips de dispar tasa de fotogramas o códec.
- **Formato de Exportación JSON:** Estructurado y completo para APIs, pero requiere serializadores CMX 3600 y FCPXML para posproducción en DaVinci Resolve.

---

## 3. Matriz de Archivos Creados y Modificados

| Archivo | Ámbito | Acción Realizada |
|---|---|---|
| `AGENTS.md` | Workspace Raíz | Protocolo de Foto Real y Validación CI/CD pre-commit ineludibles. |
| `jg_filmmaker/PROJECT_CONTEXT.md` | `jg_filmmaker` | Documentación exhaustiva v2.0 (pipeline 6 etapas, UUIDs, candados, rutas, tests y deudas). |
| `jg_filmmaker/README.md` | `jg_filmmaker` | Actualización con características implementadas Fases 1 a 6 y comandos de ejecución. |
| `jg_plugin_filmmaker/PROJECT_CONTEXT.md` | `jg_plugin_filmmaker` | Especificación técnica v2.0 con esquemas Pydantic v2 de las 3 herramientas y tests. |
| `jg_plugin_filmmaker/README.md` | `jg_plugin_filmmaker` | Guía de uso, contratos y comandos de enlace symlink con `jg_agente`. |
| `README.md` | Workspace Raíz | Topología con árbol de directorios, servicios/puertos (8010) y enlaces a repositorios. |
| `PROJECT_CONTEXT.md` | Workspace Raíz | Incorporación de la suite cinematográfica y su plugin en pilares, matriz y documentación. |
| `ECOSYSTEM_FULL_SPEC.md` | Workspace Raíz | Sección 6 completa con especificación exhaustiva de `jg_filmmaker` y `jg_plugin_filmmaker`. |
| `docs/develop/20261009-2345-fase6-documentacion-deudas-agents/` | `jg_filmmaker` | Documentación de análisis y walkthrough de la Fase 6. |
