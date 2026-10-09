# 01. Arquitectura del Sistema, Multitenancy y Modelo de Datos (UUID)

## 1. Visión General
**jg_filmmaker** es una aplicación web desacoplada en Laravel 12 que gestiona el ciclo completo de producción cinematográfica (Idea -> Estructura -> Guión -> Rodaje -> Edición -> Montaje).
Se comunica de forma asíncrona mediante HMAC-SHA256 con el runtime cognitivo `jg_agente` y consume el plugin `jg_plugin_filmmaker`.

## 2. Multitenancy y Seguridad
- **Stack Único:** Compartición eficiente de recursos en base de datos PostgreSQL.
- **Identificadores UUIDv4:** Ninguna entidad expone claves autoincrementales IDOR en rutas, APIs ni interfaces públicas. Todos los modelos Eloquent implementan el trait `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- **Aislamiento de Datos:** Todos los modelos dependientes implementan Global Scopes (`BelongsToUser` / `BelongsToProject`) y Laravel Policies asociadas a `auth()->id()`.
- **Límites de Proyecto (Cuotas):**
  - Tabla `users`: campos `plan_tier` ('free', 'pro') y `paid_project_slots` (integer).
  - Regla: Un usuario puede crear $N$ proyectos gratuitos. Para superar la cuota, la creación se valida contra `paid_project_slots` mediante un `CreateProjectAction`.

## 3. Esquema de Entidades (Migraciones)

### `projects`
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id, cascade on delete)
- `title` (string)
- `synopsis` (text, nullable)
- `status` (enum: 'draft', 'in_progress', 'completed')
- `created_at`, `updated_at`

### `characters` (Biblia de Personajes)
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `name` (string) - Indexado y único por proyecto
- `bio` (text, nullable)
- `psychology` (text, nullable)
- `costume_notes` (text, nullable)
- `custom_attributes` (jsonb, nullable)
- `created_at`, `updated_at`

### `locations` (Biblia de Localizaciones)
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `name` (string) - Ej: "BAR CENTRAL"
- `type` (enum: 'INT', 'EXT', 'INT/EXT')
- `art_department_notes` (text, nullable)
- `lighting_notes` (text, nullable)
- `address_reference` (string, nullable)
- `created_at`, `updated_at`

### `scenes`
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `scene_order` (integer)
- `slugline` (string) - Ej: "EXT. BAR - NOCHE 1" (previene colisiones de Film Manager)
- `location_id` (foreignUuid -> locations.id, nullable)
- `time_of_day` (string) - Ej: "NOCHE", "DÍA", "ATARDECER"
- `created_at`, `updated_at`

### `script_blocks` (Entidad Núcleo de Transformación)
- `id` (uuid, primary key)
- `scene_id` (foreignUuid -> scenes.id, cascade on delete)
- `block_order` (integer)
- `raw_text` (text) - Párrafo introducido por el autor
- `structured_text` (text, nullable) - Tratamiento secuencial
- `screenplay_content` (jsonb / text, nullable) - Formato Fountain / diálogo
- `user_locks` (jsonb, default: '{}') - Bloqueos y modificaciones manuales
- `stage` (enum: 'raw', 'structured', 'screenplay', 'shots', 'editing')
- `created_at`, `updated_at`

### `shots` (Planos Técnicos)
- `id` (uuid, primary key)
- `scene_id` (foreignUuid -> scenes.id, cascade on delete)
- `script_block_id` (foreignUuid -> script_blocks.id, nullable)
- `shot_number` (integer)
- `shot_type` (string) - Ej: PG, PM, PP, PLANO DETALLE
- `description` (text)
- `created_at`, `updated_at`

### `takes` (Tomas de Rodaje y Montaje)
- `id` (uuid, primary key)
- `shot_id` (foreignUuid -> shots.id, cascade on delete)
- `take_number` (integer)
- `file_path` (string, nullable) - Ruta en almacenamiento seguro
- `is_good_take` (boolean, default: false)
- `editorial_notes` (text, nullable) - Modificaciones y arreglos a realizar
- `technical_meta` (jsonb, nullable) - Duración, codec, resolución, timecode in/out
- `created_at`, `updated_at`
