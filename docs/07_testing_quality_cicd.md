# 07. Estrategia de Testing y CI/CD (`jg_filmmaker`)

[← 06. Gestión de Medios y FFmpeg](06_media_takes_ffmpeg.md) | [Volver al Índice de Documentación](README.md)

---

## 1. Pirámide de Pruebas y Fiabilidad

`jg_filmmaker` cuenta con una suite completa de pruebas unitarias y de integración desarrollada con **PHPUnit 12** dentro de la arquitectura de testing de Laravel.

Todas las pruebas garantizan:
- Inmutabilidad de los modelos y aislamiento de la base de datos mediante migraciones en memoria SQLite (`RefreshDatabase`).
- Cero efectos secundarios en el sistema de archivos mediante discos simulados (`Storage::fake('public')`).
- Cero dependencias de red externas mediante simulación de peticiones HTTP (`Http::fake()`).

---

## 2. Inventario de Pruebas Automatizadas

La suite comprende **28 pruebas y 144 aserciones** pasando al 100%:

| Archivo de Prueba | Propósito y Cobertura | Tests |
|---|---|---|
| `DomainModelArchitectureTest.php` | Valida claves primarias UUIDv4, integridad referencial, eliminación en cascada y casts JSONB (`user_locks`, `technical_meta`). | 6 |
| `ScriptEditorLivewireTest.php` | Valida renderizado del editor Livewire, alternancia de candados de usuario y apertura del drawer de Biblia. | 4 |
| `AgentClientHmacTest.php` | Valida cálculo de firma canónica HMAC-SHA256, cabeceras HTTP y tolerancia a fallos con fallback determinista. | 5 |
| `TakeMediaManagementTest.php` | Valida carga de archivos de vídeo por toma con `WithFileUploads`, marcado de `is_good_take` y persistencia física. | 5 |
| `RoughCutAssemblyTest.php` | Valida ordenación del timeline NLE, concatenación con FFmpeg, control de excepciones sin tomas aprobadas y fallback en testing. | 4 |
| `LivewirePipelineReactiveTest.php` | Valida el ciclo completo end-to-end: edición de bruto, estructuración, formateo a guión y desglose de planos. | 4 |

---

## 3. Comandos de Validación y Formateo

```bash
# 1. Ejecutar la suite completa de pruebas (28 tests)
php artisan test

# 2. Verificar formato y linter estricto de PHP con Pint
vendor/bin/pint --test

# 3. Aplicar correcciones de estilo automáticamente
vendor/bin/pint

# 4. Ejecución integrada en el pipeline global de CI/CD
bash scripts/check.sh --cicd
```

---

## 4. Deudas Técnicas Identificadas

Para preservar la transparencia operativa y orientar evoluciones futuras:
1. **Autenticación Centralizada:** Reemplazar el middleware demo local `EnsureDemoUserAuthenticated` por la pasarela SSO unificada de `jg_hub` o Laravel Fortify con pasarela de pago para cuotas de slots de proyectos (`paid_project_slots`).
2. **Normalización Multicámara en FFmpeg:** Implementar un pipeline de transcodificación intermedia previa (ej. H.264 1080p a 24fps) para proyectos donde las tomas provengan de cámaras heterogéneas.
3. **Exportadores NLE de la Industria:** Desarrollar serializadores CMX 3600 EDL y FCPXML para integración nativa con DaVinci Resolve y Final Cut Pro.
4. **Almacenamiento Distribuido:** Parametrizar la subida para admitir drivers compatibles con AWS S3 / MinIO / Cloudflare R2 en producción multi-nodo.
