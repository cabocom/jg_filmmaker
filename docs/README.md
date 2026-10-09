# Documentación Técnica de jg_filmmaker

[← Volver al README Principal](../README.md)

Este directorio contiene la documentación técnica y arquitectónica canónica de la plataforma web **jg_filmmaker**, estructurada por conceptos de dominio e ingeniería.

---

## Índice de Documentos Arquitectónicos

| Documento | Título | Descripción |
|---|---|---|
| **[01_architecture.md](01_architecture.md)** | Arquitectura General del Sistema | Visión C4, stack tecnológico (Laravel 12, Livewire 3/4, Tailwind v4), multitenancy con UUIDv4 y modelo de seguridad anti-IDOR. |
| **[02_pipeline_stages.md](02_pipeline_stages.md)** | Pipeline Cinematográfico en 6 Etapas | Flujo continuo desde el texto bruto hasta el montaje: Bruto, Tratamiento, Guión Literario, Planos Técnicos, Rodaje y Montaje NLE. |
| **[03_user_locks_governance.md](03_user_locks_governance.md)** | Gobernanza de Candados (`user_locks`) | Retención no destructiva de modificaciones manuales del autor, estructura JSONB, términos fijados (`pinned_terms`) y `UserLockResolver`. |
| **[04_context_bible.md](04_context_bible.md)** | Biblia Contextual de Proyecto | Drawer lateral interactivo (`Cmd+B` / `Ctrl+B`), gestión en caliente de Personajes y Localizaciones e inyección semántica. |
| **[05_hmac_agent_integration.md](05_hmac_agent_integration.md)** | Integración Inter-Servicio HMAC-SHA256 | Protocolo criptográfico con `jg_agente`, cabeceras canónicas (`X-Signature`), consumo de `jg_plugin_filmmaker` y fallback determinista. |
| **[06_media_takes_ffmpeg.md](06_media_takes_ffmpeg.md)** | Gestión de Medios y Ensamble FFmpeg | Almacenamiento particionado por UUID, catalogación de tomas válidas (`is_good_take`), metadatos técnicos en JSONB y motor de concatenación continua. |
| **[07_testing_quality_cicd.md](07_testing_quality_cicd.md)** | Estrategia de Testing y CI/CD | Suite de 28 tests PHPUnit, Laravel Pint, orquestación en `scripts/check.sh` y deudas técnicas conocidas. |

---

## Directorios Complementarios

- **`develop/`:** Traza histórica de evoluciones y fases de desarrollo con registros de análisis (`analysis.md`) y walkthroughs (`walkthrough.md`).
- **`specs/`:** Especificaciones funcionales de partida del proyecto.
