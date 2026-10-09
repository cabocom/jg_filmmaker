# CONTEXTO DE PROYECTO: jg_filmmaker

**Versión del documento:** 1.1.0  
**Fecha de consolidación:** 9 de octubre de 2026  
**Componente:** Plataforma Web de Pipeline Cinematográfico Asistido por IA  
**Stack Tecnológico:** Laravel 12, Livewire 3/4, Tailwind CSS 4, PHP 8.4, PostgreSQL / SQLite, PHPUnit 12, Laravel Pint  
**Estado:** Fase 1 (Arquitectura Base, Multitenancy con UUIDs y Migraciones de Datos) y Fase 2 (Interfaz Reactiva Livewire 3, Pipeline Visual y Drawer Lateral) completadas al 100%.

---

## 1. Propósito y Límites Arquitectónicos

`jg_filmmaker` es una aplicación Laravel independiente que orquesta el flujo de trabajo preproducción, rodaje y montaje cinematográfico:
1. **Desacoplamiento Estricto:** Nunca se conecta directamente a bases de datos de otros servicios como `jg_agente` o `jg_dashboard`.
2. **Protocolo HMAC-SHA256:** Cualquier comunicación con `jg_agente` para la ejecución de herramientas del plugin `jg_plugin_filmmaker` (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`) se realiza vía HTTP con firma canónica HMAC y cabeceras `X-Signature`, `X-Timestamp` y `X-Correlation-ID`.
3. **Seguridad y Cero IDOR:** Todas las tablas de dominio emplean claves UUIDv4 como claves primarias y foráneas.

---

## 2. Esquema de Entidades (Fase 1)

| Entidad | Clave Primaria | Claves Foráneas | Campos Clave y Casts |
|---|---|---|---|
| `users` | UUID | N/A | `plan_tier` ('free', 'pro'), `paid_project_slots` (integer) |
| `projects` | UUID | `user_id` -> `users.id` (cascade) | `title`, `synopsis`, `status` ('draft', 'in_progress', 'completed') |
| `characters` | UUID | `project_id` -> `projects.id` (cascade) | `name` (unique con project_id), `bio`, `psychology`, `costume_notes`, `custom_attributes` (jsonb/array) |
| `locations` | UUID | `project_id` -> `projects.id` (cascade) | `name`, `type` ('INT', 'EXT', 'INT/EXT'), notas de arte e iluminación |
| `scenes` | UUID | `project_id` -> `projects.id` (cascade), `location_id` -> `locations.id` (set null) | `scene_order` (int), `slugline`, `time_of_day` |
| `script_blocks` | UUID | `scene_id` -> `scenes.id` (cascade) | `block_order` (int), `raw_text`, `structured_text`, `screenplay_content`, `user_locks` (jsonb/array default `{}`), `stage` |
| `shots` | UUID | `scene_id` -> `scenes.id` (cascade), `script_block_id` -> `script_blocks.id` (set null) | `shot_number` (int), `shot_type`, `description` |
| `takes` | UUID | `shot_id` -> `shots.id` (cascade) | `take_number` (int), `file_path`, `is_good_take` (bool), `editorial_notes`, `technical_meta` (jsonb/array) |

---

## 3. Retención de Cambios de Usuario (`user_locks`)

El campo `user_locks` en `script_blocks` almacena la estructura JSON:
- `locked_fields`: Lista de campos bloqueados contra sobreescritura automática por IA.
- `manual_diffs`: Histórico de intervenciones manuales del usuario.
- `pinned_terms`: Mapeo de términos canónicos fijados por el autor.

---

## 4. Validación y Testing

- **Migraciones:** 10 migraciones registradas y ejecutadas con éxito.
- **Seeder:** `DemoProjectSeeder` operativo con una jerarquía completa de prueba.
- **Pruebas:** 6 pruebas y 41 aserciones en `tests/Feature/DomainModelArchitectureTest.php` pasando al 100%.
- **Linter:** `Laravel Pint` verificado sin advertencias.
