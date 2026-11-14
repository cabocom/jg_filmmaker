# 01. Arquitectura del Sistema, Multitenancy y Modelo de Datos (UUID)

## 1. Visión General
**jg_filmmaker** es una aplicación web desacoplada en Laravel 12 que gestiona el ciclo completo de producción cinematográfica (Idea -> Estructura -> Guión -> Rodaje -> Edición -> Montaje).
Se comunica de forma asíncrona mediante HMAC-SHA256 con el runtime cognitivo `jg_agente` y consume el plugin `jg_plugin_filmmaker`.

## 2. Multitenancy y Seguridad
- **Stack Único:** Compartición eficiente de recursos en base de datos PostgreSQL / SQLite.
- **Identificadores UUIDv4:** Ninguna entidad expone claves autoincrementales IDOR en rutas, APIs ni interfaces públicas. Todos los modelos Eloquent implementan el trait `Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids` (`HasUuids`).
- **Aislamiento Estricto por Tenant y Políticas de Privacidad:** Todos los modelos dependientes están estrictamente aislados por `project_id` y validados mediante Laravel Policies (`ProjectPolicy`, `ScenePolicy`, etc.). Los administradores (`is_admin = true`) pueden supervisar métricas y auditorías en `/admin`, pero tienen prohibido acceder o reproducir el material creativo o audiovisual de otros usuarios.
- **Control de Creación y Cuotas de Tokens:**
  - La creación de proyectos está gobernada por la columna booleana `users.can_create_projects`.
  - El consumo de herramientas de IA está regulado por `users.daily_token_limit`, registrando cada consumo en `agent_request_logs` con tarifas de `token_pricing_rates`.

## 3. Esquema de Entidades (14 Modelos Eloquent)

### 1. `users`
- `id` (uuid, primary key)
- `email` (string, unique)
- `is_admin` (boolean, default: false)
- `can_create_projects` (boolean, default: true)
- `daily_token_limit` (integer, default: 50000)
- `email_verified_at` (timestamp, nullable)
- `created_at`, `updated_at`

### 2. `projects`
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id, cascade on delete)
- `title` (string)
- `synopsis` (text, nullable)
- `status` (enum: 'draft', 'in_progress', 'completed')
- `final_video_path` (string, nullable)
- `created_at`, `updated_at`

### 3. `characters` (Biblia de Personajes)
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `name` (string)
- `bio` (text, nullable)
- `psychology` (text, nullable)
- `costume_notes` (text, nullable)
- `reference_image_path` (string, nullable)
- `custom_attributes` (jsonb, nullable) - Almacena voz asignada (`voice_id`), etc.
- `created_at`, `updated_at`

### 4. `locations` (Biblia de Localizaciones)
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `name` (string)
- `type` (enum: 'INT', 'EXT', 'INT/EXT')
- `art_department_notes` (text, nullable)
- `lighting_notes` (text, nullable)
- `address_reference` (string, nullable)
- `reference_image_path` (string, nullable)
- `created_at`, `updated_at`

### 5. `scenes`
- `id` (uuid, primary key)
- `project_id` (foreignUuid -> projects.id, cascade on delete)
- `scene_order` (integer)
- `slugline` (string) - Ej: "EXT. BAR - NOCHE 1"
- `location_id` (foreignUuid -> locations.id, nullable)
- `time_of_day` (string) - Ej: "NOCHE", "DÍA"
- `created_at`, `updated_at`

### 6. `script_blocks` (Entidad Núcleo de Transformación)
- `id` (uuid, primary key)
- `scene_id` (foreignUuid -> scenes.id, cascade on delete)
- `block_order` (integer)
- `raw_text` (text)
- `structured_text` (text, nullable)
- `screenplay_content` (text, nullable)
- `user_locks` (jsonb, default: '{}') - Bloqueos por etapa, términos fijados (`pinned_terms`) y justificaciones
- `stage` (enum: 'raw', 'structured', 'screenplay', 'shots', 'editing')
- `lock_version` (integer, default: 1) - Control de concurrencia optimista
- `plot_hole` (jsonb, nullable) - Detección de agujeros de guion y su estado de justificación
- `created_at`, `updated_at`

### 7. `shots` (Planos Técnicos)
- `id` (uuid, primary key)
- `scene_id` (foreignUuid -> scenes.id, cascade on delete)
- `script_block_id` (foreignUuid -> script_blocks.id, nullable)
- `shot_number` (integer)
- `shot_type` (string) - Ej: PG, PM, PP, PLANO DETALLE
- `description` (text)
- `storyboard_image_path` (string, nullable)
- `storyboard_style` (string, nullable)
- `storyboard_prompt` (text, nullable)
- `created_at`, `updated_at`

### 8. `takes` (Tomas de Rodaje)
- `id` (uuid, primary key)
- `shot_id` (foreignUuid -> shots.id, cascade on delete)
- `take_number` (integer)
- `file_path` (string, nullable) - Ruta en almacenamiento privado (`media/projects/...`)
- `is_good_take` (boolean, default: false)
- `editorial_notes` (text, nullable)
- `technical_meta` (jsonb, nullable) - Duración, codec, resolución, fps
- `created_at`, `updated_at`

### 9. `one_time_passwords` (Autenticación OTP Passwordless)
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id, cascade on delete)
- `code_hash` (string)
- `expires_at` (timestamp)
- `attempts` (integer, default: 0)
- `created_at`, `updated_at` (implementa `Prunable`)

### 10. `security_breach_logs` (Seguridad y Defensas)
- `id` (uuid, primary key)
- `ip_address` (string)
- `event_type` (string)
- `payload` (jsonb, nullable)
- `created_at`, `updated_at`

### 11. `admin_audit_logs` (Auditoría Forense de Administración)
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id) - Administrador que ejecuta la acción
- `action` (string) - Ej: 'transfer_project', 'delete_user'
- `target_type` (string)
- `target_id` (string)
- `meta` (jsonb, nullable)
- `ip_address` (string)
- `created_at`, `updated_at`

### 12. `user_activity_logs` (Trazabilidad Operativa)
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id, nullable)
- `action` (string)
- `subject_type` (string, nullable)
- `subject_id` (string, nullable)
- `description` (string)
- `meta` (jsonb, nullable)
- `ip_address` (string, nullable)
- `created_at`, `updated_at` (implementa `Prunable`)

### 13. `agent_request_logs` (Telemetría de Herramientas IA)
- `id` (uuid, primary key)
- `user_id` (foreignUuid -> users.id, nullable)
- `project_id` (foreignUuid -> projects.id, nullable)
- `script_block_id` (foreignUuid -> script_blocks.id, nullable)
- `tool_name` (string)
- `provider` (string)
- `model` (string)
- `input_tokens` (integer)
- `output_tokens` (integer)
- `cost_usd` (decimal)
- `duration_ms` (integer)
- `status` (string)
- `meta` (jsonb, nullable)
- `created_at`, `updated_at`

### 14. `token_pricing_rates` (Tarificación Temporal de Modelos)
- `id` (uuid, primary key)
- `provider` (string)
- `model` (string)
- `input_cost_per_million` (decimal)
- `output_cost_per_million` (decimal)
- `effective_from` (timestamp)
- `created_at`, `updated_at`
