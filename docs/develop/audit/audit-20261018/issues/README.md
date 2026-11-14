# Índice General de Issues de Auditoría - `jg_filmmaker` & `jg_plugin_filmmaker`

**Fecha de Auditoría:** 18 de octubre de 2026  
**Ámbito de Inspección:** `jg_filmmaker/` (Laravel 12 / Livewire 4) y `jg_plugin_filmmaker/` (Python / Pydantic v2 / FastAPI)  
**Total de Hallazgos:** 42 issues documentados  

---

## Resumen Ejecutivo por Severidad

| Severidad | Total | Porcentaje | Descripción de Riesgo |
|---|:---:|:---:|---|
| **Crítica** | 3 | 7.1% | Vulnerabilidades de ejecución remota de código (RCE), divulgación de OTP/toma de cuentas de administrador y bypass total de autorización multitenant (IDOR). |
| **Alta** | 9 | 21.4% | Fallos de diseño en autorización secundaria, concatenación FFmpeg rota con falsos positivos, ReDoS en expresiones regulares, degradación no controlada de IA y omisión de cuotas. |
| **Media** | 27 | 64.3% | Riesgos de concurrencia (SQLite, pérdidas de actualización en locks), N+1 queries, discrepancias documentales/specs y gaps en suites de tests. |
| **Baja** | 3 | 7.2% | Deuda técnica de diseño monolítico, duplicación heurística y retención ilimitada de logs de actividad. |
| **TOTAL** | **42** | **100%** | |

---

## Tabla Consolidada de Hallazgos

### 1. Severidad Crítica (3 Issues)

| ID | Título | Componente | Categoría | Archivo del Issue |
|---|---|---|---|---|
| **ISSUE-001** | Subida de tomas permite extensión arbitraria controlada por el cliente (RCE vía `.php` en `/storage`) | `jg_filmmaker` | Seguridad | [ISSUE-001-sec-rce-take-upload-extension.md](./ISSUE-001-sec-rce-take-upload-extension.md) |
| **ISSUE-002** | Divulgación del OTP de login mediante propiedad Livewire manipulable (`isLocalEnv`) y cabecera `Host`: toma de cuenta (incl. admin) | `jg_filmmaker` | Seguridad | [ISSUE-002-sec-otp-disclosure-client-controlled-flag.md](./ISSUE-002-sec-otp-disclosure-client-controlled-flag.md) |
| **ISSUE-003** | Ausencia total de autorización por propietario: `/projects/{project}` y `ScriptEditor` operan con IDs sin acotar (IDOR multitenant) | `jg_filmmaker` | Seguridad | [ISSUE-003-sec-idor-project-ownership-script-editor.md](./ISSUE-003-sec-idor-project-ownership-script-editor.md) |

---

### 2. Severidad Alta (9 Issues)

| ID | Título | Componente | Categoría | Archivo del Issue |
|---|---|---|---|---|
| **ISSUE-004** | IDOR en `ContextBibleDrawer` y `RoughCutTimeline` (lectura/edición de personajes, localizaciones y tomas ajenas) | `jg_filmmaker` | Seguridad | [ISSUE-004-sec-idor-bible-and-timeline.md](./ISSUE-004-sec-idor-bible-and-timeline.md) |
| **ISSUE-005** | `reorderBlock()` permite mover bloques de otros proyectos a una escena propia (robo/destrucción de guion) | `jg_filmmaker` | Seguridad | [ISSUE-005-sec-cross-tenant-block-move-reorder.md](./ISSUE-005-sec-cross-tenant-block-move-reorder.md) |
| **ISSUE-006** | Vídeos de tomas en disco `public` sin autenticación y UUID realmente v7 (no v4 como documenta la spec) | `jg_filmmaker` | Seguridad | [ISSUE-006-sec-public-media-disk-uuidv7.md](./ISSUE-006-sec-public-media-disk-uuidv7.md) |
| **ISSUE-007** | `docker-compose.yml` arranca con `APP_KEY` y secreto HMAC por defecto en el repositorio, `APP_ENV=local`, `APP_DEBUG=true` y demo sembrada | `jg_filmmaker` | Seguridad | [ISSUE-007-sec-insecure-compose-defaults.md](./ISSUE-007-sec-insecure-compose-defaults.md) |
| **ISSUE-008** | Autenticación OTP: registro abierto, rate-limit solo por IP, doble uso por condición de carrera y coste CPU/almacenamiento no acotado | `jg_filmmaker` | Seguridad | [ISSUE-008-sec-otp-bruteforce-race-open-registration.md](./ISSUE-008-sec-otp-bruteforce-race-open-registration.md) |
| **ISSUE-009** | Ensamblado FFmpeg: reintento usa lista concat ya borrada, éxito falso con datos simulados, ejecución síncrona sin límites y colisión de nombres | `jg_filmmaker` | Robustez / Bugs | [ISSUE-009-robust-ffmpeg-retry-deleted-list-false-success.md](./ISSUE-009-robust-ffmpeg-retry-deleted-list-false-success.md) |
| **ISSUE-010** | `AgentClientService::callTool` traga todos los errores y salta a un LLM directo con clave global, saltándose `jg_agente`, telemetría y cuota | `jg_filmmaker` | Arquitectura | [ISSUE-010-arch-agent-client-silent-failures-direct-llm-bypass.md](./ISSUE-010-arch-agent-client-silent-failures-direct-llm-bypass.md) |
| **ISSUE-011** | `plot_hole_detector` no existe en `jg_plugin_filmmaker` ni en la spec: la auditoría de agujeros nunca llega al agente | `jg_filmmaker` / `jg_plugin_filmmaker` | Arquitectura | [ISSUE-011-arch-plot-hole-detector-tool-missing-in-plugin.md](./ISSUE-011-arch-plot-hole-detector-tool-missing-in-plugin.md) |
| **ISSUE-012** | Contexto de Biblia roto: atributos inexistentes (`role`, `personality_notes`, `description`) y claves incompatibles con el plugin (`bio`) | `jg_filmmaker` / `jg_plugin_filmmaker` | Robustez / Bugs | [ISSUE-012-bug-bible-context-missing-attributes-contract-mismatch.md](./ISSUE-012-bug-bible-context-missing-attributes-contract-mismatch.md) |
| **ISSUE-013** | Pérdida de `user_locks` por actualización perdida (read-modify-write sin control de concurrencia alrededor de llamadas IA de 30-45 s) | `jg_filmmaker` | Arquitectura | [ISSUE-013-arch-user-locks-lost-update-race.md](./ISSUE-013-arch-user-locks-lost-update-race.md) |
| **ISSUE-017** | El control de cuota diaria de tokens sólo se aplica a la auditoría de agujeros y es vulnerable a check-then-act | `jg_filmmaker` | Robustez / Bugs | [ISSUE-017-robust-token-quota-not-enforced.md](./ISSUE-017-robust-token-quota-not-enforced.md) |
| **ISSUE-035** | Expresiones regulares con backtracking polinómico y entradas sin límite de tamaño (ReDoS / DoS de CPU y tokens) | `jg_plugin_filmmaker` | Rendimiento | [ISSUE-035-perf-plugin-regex-backtracking-unbounded-input.md](./ISSUE-035-perf-plugin-regex-backtracking-unbounded-input.md) |

---

### 3. Severidad Media (27 Issues)

| ID | Título | Componente | Categoría | Archivo del Issue |
|---|---|---|---|---|
| **ISSUE-014** | La telemetría IA (prompts y respuestas completas) se guarda dentro de `user_locks` y se reenvía al agente en cada ejecución | `jg_filmmaker` | Arquitectura | [ISSUE-014-arch-ai-telemetry-inside-user-locks-bloat.md](./ISSUE-014-arch-ai-telemetry-inside-user-locks-bloat.md) |
| **ISSUE-015** | Regeneración destruye `manual_diffs`/candados y el desglose de planos ignora `locked_fields` | `jg_filmmaker` | Arquitectura | [ISSUE-015-arch-regeneration-destroys-diffs-shots-ignore-locks.md](./ISSUE-015-arch-regeneration-destroys-diffs-shots-ignore-locks.md) |
| **ISSUE-016** | `pinned_terms`: el reemplazo se interpreta como expresión regular (backreferences) en PHP y Python, y no hay UI para crearlos | `jg_filmmaker` / `jg_plugin_filmmaker` | Robustez / Bugs | [ISSUE-016-bug-pinned-terms-regex-replacement-backrefs.md](./ISSUE-016-bug-pinned-terms-regex-replacement-backrefs.md) |
| **ISSUE-018** | Propiedades públicas Livewire manipulables y valores de `stage`/`tab`/`stage-lock` sin validar | `jg_filmmaker` | Seguridad | [ISSUE-018-sec-livewire-public-props-unvalidated-state.md](./ISSUE-018-sec-livewire-public-props-unvalidated-state.md) |
| **ISSUE-019** | `ScriptEditor::mount()` ejecuta escrituras y consultas N+1 en cada carga (y tras cada borrado de escena) | `jg_filmmaker` | Rendimiento | [ISSUE-019-perf-mount-sync-writes-n-plus-one.md](./ISSUE-019-perf-mount-sync-writes-n-plus-one.md) |
| **ISSUE-020** | La sincronización automática de la Biblia borra localizaciones del usuario y crea personajes falsos a partir de listas fijas | `jg_filmmaker` | Robustez / Bugs | [ISSUE-020-bug-bible-autosync-deletes-user-data.md](./ISSUE-020-bug-bible-autosync-deletes-user-data.md) |
| **ISSUE-021** | Panel de administración: sin autorización en las acciones, sin salvaguardas ni auditoría (`toggleAdmin`, `clearLogs`, transferencias) | `jg_filmmaker` | Seguridad | [ISSUE-021-sec-admin-actions-no-authz-no-audit.md](./ISSUE-021-sec-admin-actions-no-authz-no-audit.md) |
| **ISSUE-022** | Consultas administrativas sin paginar, agregaciones duplicadas y sin índices adecuados | `jg_filmmaker` | Rendimiento | [ISSUE-022-perf-admin-queries-unpaginated-aggregations.md](./ISSUE-022-perf-admin-queries-unpaginated-aggregations.md) |
| **ISSUE-023** | Numeración (`block_order`, `scene_order`, `shot_number`, `take_number`) calculada con `max()+1` sin restricción única ni transacción | `jg_filmmaker` | Robustez / Bugs | [ISSUE-023-robust-numbering-races-no-unique-constraints.md](./ISSUE-023-robust-numbering-races-no-unique-constraints.md) |
| **ISSUE-024** | Render Livewire carga el grafo completo del proyecto en cada interacción y el layout agrega tokens en cada petición | `jg_filmmaker` | Rendimiento | [ISSUE-024-perf-render-full-graph-and-layout-aggregate.md](./ISSUE-024-perf-render-full-graph-and-layout-aggregate.md) |
| **ISSUE-025** | Ficheros de vídeo huérfanos: el borrado en cascada de BD no dispara `Take::deleting` ni limpia el disco | `jg_filmmaker` | Robustez / Bugs | [ISSUE-025-robust-orphan-media-files-cascade.md](./ISSUE-025-robust-orphan-media-files-cascade.md) |
| **ISSUE-026** | Subida temporal de Livewire limitada a 12 MB y sin throttle propio, incoherente con la validación de 100 MB y el límite de nginx de 512 MB | `jg_filmmaker` | Robustez / Bugs | [ISSUE-026-robust-upload-limits-inconsistent.md](./ISSUE-026-robust-upload-limits-inconsistent.md) |
| **ISSUE-027** | Metadatos técnicos de tomas inventados (duración 5 s, 1080p, 24 fps) sin sondeo real del vídeo | `jg_filmmaker` | Robustez / Bugs | [ISSUE-027-bug-fabricated-technical-meta-no-ffprobe.md](./ISSUE-027-bug-fabricated-technical-meta-no-ffprobe.md) |
| **ISSUE-028** | Middleware `EnsureDemoUserAuthenticated` autologuea como admin en entorno `testing` y está registrado globalmente; enmascara la autenticación en las pruebas | `jg_filmmaker` | Seguridad | [ISSUE-028-sec-demo-autologin-middleware-masks-auth.md](./ISSUE-028-sec-demo-autologin-middleware-masks-auth.md) |
| **ISSUE-029** | Suite PHPUnit: sin `preventStrayRequests`, sin forzar `JG_AGENTE_MOCK`, sin pruebas de tenencia/IDOR y CI sobre `.env.example` | `jg_filmmaker` | Calidad de Código | [ISSUE-029-quality-tests-no-stray-requests-no-tenancy-tests.md](./ISSUE-029-quality-tests-no-stray-requests-no-tenancy-tests.md) |
| **ISSUE-030** | Lectura directa de `.env` y `env()` fuera de `config/`: rompe `config:cache`, mezcla credenciales y selecciona claves por heurística | `jg_filmmaker` | Seguridad | [ISSUE-030-sec-env-parsing-api-key-misrouting-config-cache.md](./ISSUE-030-sec-env-parsing-api-key-misrouting-config-cache.md) |
| **ISSUE-031** | Diseño de la autenticación HMAC inter-servicio: secreto compartido único, sin firma de respuesta, query/cabeceras fuera de la firma y sin verificación de contrato | `jg_filmmaker` / `jg_plugin_filmmaker` | Seguridad | [ISSUE-031-sec-hmac-shared-secret-design.md](./ISSUE-031-sec-hmac-shared-secret-design.md) |
| **ISSUE-032** | SQLite como base de datos de producción con peticiones largas y escrituras en lecturas: riesgo de `database is locked` | `jg_filmmaker` | Arquitectura | [ISSUE-032-arch-sqlite-production-concurrency.md](./ISSUE-032-arch-sqlite-production-concurrency.md) |
| **ISSUE-033** | Discrepancias entre la documentación (`docs/`, `docs/specs/`) y la implementación real | `jg_filmmaker` / `jg_plugin_filmmaker` | Calidad de Código | [ISSUE-033-docs-spec-vs-implementation-discrepancies.md](./ISSUE-033-docs-spec-vs-implementation-discrepancies.md) |
| **ISSUE-034** | `docker-compose.yml` viola el despliegue inmutable: `build:` local, bind mounts del código y `RUN_MIGRATIONS`/seed en arranque | `jg_filmmaker` | Arquitectura | [ISSUE-034-arch-compose-violates-immutable-deploy.md](./ISSUE-034-arch-compose-violates-immutable-deploy.md) |
| **ISSUE-036** | Pydantic v2 poco estricto: `dict[str, Any]` sin esquema, funciones `execute_*` que aceptan datos sin validar y `except Exception` que oculta errores | `jg_plugin_filmmaker` | Calidad de Código | [ISSUE-036-quality-plugin-pydantic-loose-typing.md](./ISSUE-036-quality-plugin-pydantic-loose-typing.md) |
| **ISSUE-037** | `llm.py`: timeout ineficaz, ausencia de timeout en `.invoke()`, acoplamiento a `app.core.llm` y modo mock inferido de variables implícitas | `jg_plugin_filmmaker` | Arquitectura | [ISSUE-037-arch-plugin-llm-timeouts-coupling-mock-detection.md](./ISSUE-037-arch-plugin-llm-timeouts-coupling-mock-detection.md) |
| **ISSUE-039** | El `docker-compose.yml` del plugin reproduce todo el ecosistema con credenciales débiles por defecto y mezcla responsabilidades | `jg_plugin_filmmaker` | Seguridad | [ISSUE-039-sec-plugin-compose-default-credentials-scope.md](./ISSUE-039-sec-plugin-compose-default-credentials-scope.md) |
| **ISSUE-042** | Cobertura insuficiente en el plugin: 13 tests sin casos de error, límites, locks adversariales ni contrato con el cliente PHP | `jg_plugin_filmmaker` | Calidad de Código | [ISSUE-042-quality-plugin-test-coverage-gaps.md](./ISSUE-042-quality-plugin-test-coverage-gaps.md) |

---

### 4. Severidad Baja (3 Issues)

| ID | Título | Componente | Categoría | Archivo del Issue |
|---|---|---|---|---|
| **ISSUE-038** | `refine_dialogue_to_direct_speech` lanza `IndexError` con diálogos vacíos tras purgar signos | `jg_plugin_filmmaker` | Robustez / Bugs | [ISSUE-038-bug-plugin-refine-dialogue-indexerror.md](./ISSUE-038-bug-plugin-refine-dialogue-indexerror.md) |
| **ISSUE-040** | `user_activity_logs` almacena snapshots completos del guion sin retención ni límite de tamaño | `jg_filmmaker` | Rendimiento | [ISSUE-040-perf-activity-log-snapshots-unbounded-retention.md](./ISSUE-040-perf-activity-log-snapshots-unbounded-retention.md) |
| **ISSUE-041** | Clases monolíticas y lógica heurística duplicada entre `AgentClientService` (PHP) y `jg_plugin_filmmaker` (Python) | `jg_filmmaker` / `jg_plugin_filmmaker` | Calidad de Código | [ISSUE-041-quality-monolithic-classes-duplicated-heuristics.md](./ISSUE-041-quality-monolithic-classes-duplicated-heuristics.md) |
