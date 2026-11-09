# Documentación Técnica — `jg_filmmaker`

Plataforma Web de Creación Cinematográfica Asistida por IA del ecosistema `javiergalvez-ia`.

---

## 🌐 Estructura Multilingüe

La documentación técnica de `jg_filmmaker` está organizada por directorios de idioma (ISO 639-1):

- 🇪🇸 **[Español (Fuente Canónica / SSOT)](es/)**:
  - [01. Arquitectura General del Sistema](es/01_architecture.md)
  - [02. Pipeline Cinematográfico en 6 Etapas](es/02_pipeline_stages.md)
  - [03. Gobernanza de Candados (`user_locks`)](es/03_user_locks_governance.md)
  - [04. Biblia Contextual de Proyecto](es/04_context_bible.md)
  - [05. Integración Inter-Servicio HMAC-SHA256](es/05_hmac_agent_integration.md)
  - [06. Gestión de Medios y Ensamble FFmpeg](es/06_media_takes_ffmpeg.md)
  - [07. Estrategia de Testing y CI/CD](es/07_testing_quality_cicd.md)
  - [08. Autenticación Passwordless, Panel de Administración y Telemetría](es/08_auth_admin_telemetry.md)
  - [Historia y Evolución del Proyecto](es/TODO_HISTORIA_EVOLUCION.md)
  - [Walkthrough Inicial](es/walkthrough-initial.md)

## 🛡️ Auditorías Técnicas y Refactorización v2.0

- [Auditoría Técnica integral 2026-11-09 (`audit-20261109`)](audit/audit-20261109/audit-20261109.md) — [Índice de 27 Issues (`#6` a `#32`)](audit/audit-20261109/issues/README.md)
- [Auditoría Técnica 2026-10-18 (`audit-20261018`)](audit/audit-20261018/audit-20261018.md)
- [Plan de Refactorización Estructural SOLID v2.0 (`#33`)](issues/20261109_refactor_v2_solid_architecture.md)

---

## Compilación Unificada

Para compilar la documentación consolidada de `jg_filmmaker` para IAs externas y desarrolladores:
```bash
# Compilar versión canónica (español)
./workspace.sh docs jg_filmmaker --locale=es

# Compilar versión en inglés (con fallback automático)
./workspace.sh docs jg_filmmaker --locale=en

# Compilar todos los idiomas
./workspace.sh docs jg_filmmaker --locale=all
```
