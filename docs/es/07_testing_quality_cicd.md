# 07. Estrategia de Testing y CI/CD (`jg_filmmaker`)

[← 06. Gestión de Medios y FFmpeg](06_media_takes_ffmpeg.md) | [Volver al Índice de Documentación](README.md)

---

## 1. Pirámide de Pruebas y Fiabilidad

`jg_filmmaker` cuenta con una suite completa de pruebas unitarias y de integración desarrollada con **PHPUnit 12** dentro de la arquitectura de testing de Laravel.

Todas las pruebas garantizan:
- Inmutabilidad de los modelos y aislamiento de la base de datos mediante migraciones en memoria SQLite (`RefreshDatabase`).
- Cero efectos secundarios en el sistema de archivos mediante discos simulados (`Storage::fake('public')`).
- Cero fugas de red y aislamiento estricto mediante `Http::preventStrayRequests()` y simulación de peticiones HTTP (`Http::fake()`).
- Ejecución determinista forzada en testing (`services.jg_agente.mock = true`, `JG_AGENTE_MOCK=true`, `JG_AGENTE_URL=http://agent.invalid`, `GROQ_API_KEY=""`).

---

## 2. Inventario de Pruebas Automatizadas

La suite comprende **148 pruebas y 708 aserciones** pasando al 100%:

| Archivo de Prueba | Propósito y Cobertura | Tests |
|---|---|---|
| `DomainModelArchitectureTest.php` | Valida claves primarias UUIDv4, integridad referencial, eliminación en cascada y casts JSONB (`user_locks`, `technical_meta`). | 6 |
| `TakeMediaUploadTest.php` | Valida carga de archivos de vídeo por toma con `WithFileUploads`, marcado de `is_good_take` y persistencia física. | 4 |
| `RoughCutTimelineTest.php` | Valida reactividad del timeline, filtrado de tomas aprobadas y persistencia de notas editoriales. | 3 |
| `RoughCutAssemblyTest.php` | Valida ordenación del timeline NLE, concatenación con FFmpeg, control de excepciones sin tomas aprobadas y fallback en testing. | 4 |
| `AgentClientHmacSignatureTest.php` | Valida cálculo de firma canónica HMAC-SHA256, cabeceras HTTP y tolerancia a fallos con fallback determinista. | 5 |
| `AgentClientRobustnessTest.php` | Valida logging de advertencias, mitigación de degradación y bypass preventivo ante caídas del agente. | 5 |
| `ScriptPipelineIntegrationTest.php` | Valida el ciclo completo end-to-end: edición de bruto, estructuración, formateo a guión y desglose de planos. | 6 |
| `LivewireProjectDashboardTest.php` | Valida catálogo inicial de proyectos, creación rápida y métricas de cuota. | 2 |
| `LivewireScriptEditorTest.php` | Valida editor continuo, acordeón StoryArchitect, divisores Gutenberg, drawer lateral al 60%, tomas y candados. | 21 |
| `PasswordlessAuthTest.php` | Valida flujo de OTP, banner especial en localhost, un solo uso y detección de replay attacks con registros de brecha. | 6 |
| `AdminPanelAndTelemetryTest.php` | Valida middleware 403, auditoría cuantitativa sin fuga de textos literarios, roles de admin y registro de telemetría de costes en USD. | 5 |
| `ProjectTransferAdminTest.php` | Valida reasignación y transferencia atómica de obras entre usuarios desde `UserDetail` y `ProjectManager`. | 4 |
| `MultiTenantProjectIsolationTest.php` | Valida aislamiento estricto IDOR entre autores para proyectos, escenas y bloques dramáticos. | 5 |
| `BlockReorderCrossTenantIsolationTest.php` | Valida prevención de movimientos y reordenaciones cruzadas de bloques entre escenas de distintos inquilinos. | 4 |
| `ContextBibleAndTimelineIsolationTest.php` | Valida aislamiento multi-tenant de personajes, localizaciones y tomas del timeline. | 5 |
| `TokenQuotaEnforcementTest.php` | Valida cuotas diarias de tokens de IA por usuario, excepciones `QuotaExceededException` e imputación indeleble. | 4 |
| `UserLocksConcurrencyTest.php` | Valida concurrencia optimista/pesimista y prevención de lost updates en `user_locks`. | 5 |
| `UserLocksPreservationTest.php` | Valida preservación de candados, archivado no destructivo de diffs y protección de planos con tomas. | 5 |
| `UserLocksTelemetryExtractionTest.php` | Valida desacoplamiento de telemetría cognitiva fuera de `user_locks` a `agent_request_logs.meta`. | 4 |
| `PinnedTermsSafetyTest.php` | Valida sustitución literal de términos fijos sin evaluar backreferences `$1` ni escapes octales. | 3 |
| `ReDosMitigationTest.php` | Valida protección contra backtracking catastrófico, cuantificadores acotados y resiliencia ante diálogos residuales vacíos. | 4 |
| `ContractParityTest.php` | Valida paridad contractual bidireccional contra golden fixtures compartidos entre Laravel y el plugin satélite de Python. | 4 |

---

## 3. Comandos de Validación y Formateo

```bash
# 1. Ejecutar la suite completa de pruebas (148 tests)
php artisan test

# 2. Verificar formato y linter estricto de PHP con Pint
vendor/bin/pint --test

# 3. Aplicar correcciones de estilo automáticamente
vendor/bin/pint

# 4. Ejecución integrada en el pipeline global de CI/CD
bash scripts/check.sh --cicd -p jg_filmmaker
```

---

## 4. Pipeline GitLab CI/CD y Despliegue con Docker

El proyecto dispone de un pipeline de integración continua configurado en `.gitlab-ci.yml` con dos etapas estandarizadas:
1. **Stage `test` (`test:phpunit`):** Imagen `composer:2`, instalación determinista de dependencias (`composer install --prefer-dist`) y ejecución de la suite completa de pruebas automatizadas.
2. **Stage `build` (`build:docker`):** Construcción multi-stage de contenedor Docker limpio (sin ofuscación) con PHP 8.4-FPM, Nginx, FFmpeg y Node 22 (Vite), publicando etiquetas `:clean`, `:latest` y la rama de trabajo.

### Despliegue Local con `docker-compose.yml`
```bash
# Levantar el servicio en el puerto 8010
docker-compose up -d --build

# Comprobar salud del contenedor
curl -f http://127.0.0.1:8010/up
```

---

## 5. Deudas Técnicas Identificadas

Para preservar la transparencia operativa y orientar evoluciones futuras:
1. **Autenticación Centralizada:** Reemplazar el middleware demo local `EnsureDemoUserAuthenticated` por la pasarela SSO unificada de `jg_hub` o Laravel Fortify con pasarela de pago para cuotas de slots de proyectos (`paid_project_slots`).
2. **Normalización Multicámara en FFmpeg:** Implementar un pipeline de transcodificación intermedia previa (ej. H.264 1080p a 24fps) para proyectos donde las tomas provengan de cámaras heterogéneas.
3. **Exportadores NLE de la Industria:** Desarrollar serializadores CMX 3600 EDL y FCPXML para integración nativa con DaVinci Resolve y Final Cut Pro.
4. **Almacenamiento Distribuido:** Parametrizar la subida para admitir drivers compatibles con AWS S3 / MinIO / Cloudflare R2 en producción multi-nodo.
