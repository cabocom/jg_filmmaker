# Walkthrough Técnico: Inicialización de Arquitectura, Multitenancy con UUIDs y Migraciones de Datos

**Proyecto:** `jg_filmmaker`  
**Fecha:** 9 de octubre de 2026  
**Versión:** 1.0.0 (Fase 1 completada)  
**Stack:** Laravel 12 (`v12.69.3`), PHP 8.4, Eloquent ORM, PHPUnit 12, Laravel Pint  

---

## 1. Prompt Recibido

A continuación se transcribe textualmente el requerimiento recibido que originó esta fase de trabajo:

```markdown
[PROJECT: jg_filmmaker] - Fase 1: Arquitectura Base, Multitenancy con UUIDs y Migraciones de Datos

Actúa como Ingeniero de Software Senior especializado en Laravel 12 y arquitecturas desacopladas del ecosistema jg_ia.

Estamos iniciando el desarrollo de la aplicación web jg_filmmaker (directorio jg_filmmaker dentro del workspace javiergalvez-ia).

Antes de comenzar a programar, lee con atención las especificaciones técnicas completas ubicadas en:
- docs/specs/01_architecture_and_models.md
- docs/specs/02_pipeline_stages_and_locks.md
- docs/specs/03_jg_plugin_filmmaker_contract.md
- docs/specs/04_shots_takes_media_management.md

### DIRECTRICES TÉCNICAS OBLIGATORIAS:
1. Multitenancy y Seguridad por UUID:
   - Todas las tablas de dominio (projects, characters, locations, scenes, script_blocks, shots, takes) deben utilizar UUIDv4 como clave primaria ($table->uuid('id')->primary()).
   - Queda terminantemente prohibido exponer IDs autoincrementales IDOR en rutas, APIs o claves foráneas públicas.
   - Todos los modelos Eloquent deben implementar el trait `Illuminate\Database\Eloquent\Concerns\HasUuids`.
   - Asegura la cascada en borrados y claves foráneas tipadas con `foreignUuid()`.

2. Modelo de Transformación (ScriptBlocks y UserLocks):
   - Implementa la tabla `script_blocks` con las columnas exactas especificadas, incluyendo `raw_text`, `structured_text`, `screenplay_content`, `stage` y el campo JSONB `user_locks` con valor por defecto `{}`.

3. Biblia Contextual (Characters & Locations):
   - Prevé los modelos y relaciones directas con `Project` para soportar acceso y consulta contextual sin acoplamiento a una escena fija.

4. Desglose y Tomas:
   - Implementa `shots` y `takes` soportando el almacenamiento de metadatos técnicos en JSONB (`technical_meta`) y observaciones editoriales (`editorial_notes`).

5. Regla de Oro del Ecosistema jg_ia:
   - Recuerda que jg_filmmaker es una aplicación Laravel independiente que solo interactuará con jg_agente a través de HTTP firmado con HMAC-SHA256 (nunca mediante conexiones directas a las bases de datos de jg_agente).

### OBJETIVO DE ESTE PRIMER PASO:
1. Crea las migraciones correspondientes en database/migrations/ para todas las entidades descritas en 01_architecture_and_models.md.
2. Crea los Modelos Eloquent en app/Models/ con sus respectivos HasUuids, `$fillable`, `$casts` (especialmente para los campos jsonb `user_locks` y `technical_meta`), y relaciones `hasMany` / `belongsTo`.
3. Crea los Seeders / Factories mínimos para poder levantar un proyecto de prueba con un usuario, una escena, un bloque de guion, un plano y una toma.

Indícame los archivos generados y ejecuta las migraciones para validar que el esquema queda perfectamente montado sin errores.
```

---

## 2. Análisis Previo de Ingeniería y Decisiones de Diseño

Antes de modificar el código, se examinó el directorio `jg_filmmaker/docs/specs/` y el contexto global del workspace `javiergalvez-ia`:

### 2.1. Desacoplamiento y Fronteras del Ecosistema (`Rule 4`)
- `jg_filmmaker` es un servicio independiente (al igual que `jg_dashboard`, `jg_hub` o `jg_agente`).
- **Aislamiento de Persistencia:** No comparte bases de datos con `jg_agente`. En fases posteriores, cualquier llamada para enriquecimiento o estructuración de guión hacia el plugin `jg_plugin_filmmaker` se realizará mediante peticiones HTTP autenticadas con **HMAC-SHA256** (`X-Signature`, `X-Timestamp`, `X-Correlation-ID`).

### 2.2. Seguridad Anti-IDOR y Multitenancy
- Para eliminar vulnerabilidades de Insecure Direct Object References (IDOR), se decidió que **incluso la tabla `users` y las sesiones** utilicen `UUIDv4`, de modo que no exista ningún identificador entero autoincremental en las relaciones públicas de dominio.
- Todos los modelos Eloquent de dominio adoptan el trait estándar `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Las claves foráneas se definieron mediante `$table->foreignUuid(...)` con reglas explícitas de integridad referencial:
  - Cascada estricta (`cascadeOnDelete()`) para dependencias verticales (`User -> Project -> Scenes -> ScriptBlocks -> Shots -> Takes`).
  - Nulabilidad segura (`nullOnDelete()`) para referencias desacoplables opcionales (`scenes.location_id` y `shots.script_block_id`).

### 2.3. Estructura de `ScriptBlock` y el Mecanismo `user_locks`
- La entidad central de transformación (`ScriptBlock`) debe retener el progreso del usuario a lo largo de las 5 fases: `raw` -> `structured` -> `screenplay` -> `shots` -> `editing`.
- La columna `screenplay_content` se configuró como `text` para albergar indistintamente sintaxis estándar cinematográfica Fountain (Markdown de guionistas) o bloques estructurados serializados.
- La columna `user_locks` se configuró como tipo nativo `jsonb` con valor por defecto `'{}'`, mapeada con un cast Eloquent `'user_locks' => 'array'` para garantizar manipulación transparente en PHP de las claves `locked_fields`, `manual_diffs` y `pinned_terms`.

### 2.4. Biblia Contextual (`Character` y `Location`)
- Los personajes y localizaciones no pertenecen a escenas individuales, sino directamente a `Project`. Esto permite que el pipeline de IA inyecte todo el contexto semántico de la producción cinematográfica antes de procesar cualquier escena.
- Se añadió un índice único compuesto en `characters`: `$table->unique(['project_id', 'name'])` para prevenir nombres duplicados dentro del mismo proyecto cinematográfico.

### 2.5. Gestión de Tomas y Metadatos Técnicos (`Take`)
- Cada toma (`Take`) almacena `is_good_take` (filtro primario para montaje de corte previo / EDL), `editorial_notes` (anotaciones del director/script) y `technical_meta` (JSONB mapeado a array para duración exacta, códec, resolución, fps y timecodes).

---

## 3. Walkthrough de Implementación

### Paso 1: Configuración del Esqueleto Laravel 12 y Dependencias
- Se inicializó el proyecto Laravel con soporte para **Laravel 12** (`laravel/framework: ^12.0`) y PHP 8.4 en [`composer.json`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/composer.json).
- Se ejecutó `composer install` resolviendo de forma exitosa todas las dependencias requeridas (`laravel/framework: v12.69.3`, `laravel/tinker`, `laravel/pint`, `phpunit/phpunit`).
- Se configuró la clave de cifrado de la aplicación con `php artisan key:generate`.
- Se adaptó `.gitignore` para ignorar bases de datos temporales SQLite y cachés de pruebas sin comprometer el control de versiones.

### Paso 2: Migración Base de Usuarios con UUID
- Se actualizó [`database/migrations/0001_01_01_000000_create_users_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/0001_01_01_000000_create_users_table.php):
  - Clave primaria UUID: `$table->uuid('id')->primary()`.
  - Campos de cuota multi-inquilino: `plan_tier` (string con default 'free') y `paid_project_slots` (integer con default 0).
  - Tabla `sessions` adaptada con `$table->foreignUuid('user_id')->nullable()->index()`.

### Paso 3: Creación de Migraciones de Dominio
Se generaron las migraciones en orden topológico estricto para satisfacer las claves foráneas:

1. **`projects`** ([`2026_10_09_200001_create_projects_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200001_create_projects_table.php)):
   - `id` (uuid, pk)
   - `user_id` (foreignUuid -> users.id, cascade on delete)
   - `title` (string)
   - `synopsis` (text, nullable)
   - `status` (enum: 'draft', 'in_progress', 'completed', default 'draft')

2. **`characters`** ([`2026_10_09_200002_create_characters_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200002_create_characters_table.php)):
   - `id` (uuid, pk)
   - `project_id` (foreignUuid -> projects.id, cascade on delete)
   - `name` (string)
   - `bio`, `psychology`, `costume_notes` (text, nullable)
   - `custom_attributes` (jsonb, nullable)
   - Restricción de unicidad: `$table->unique(['project_id', 'name'])`

3. **`locations`** ([`2026_10_09_200003_create_locations_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200003_create_locations_table.php)):
   - `id` (uuid, pk)
   - `project_id` (foreignUuid -> projects.id, cascade on delete)
   - `name` (string)
   - `type` (enum: 'INT', 'EXT', 'INT/EXT', default 'INT')
   - `art_department_notes`, `lighting_notes`, `address_reference` (text/string, nullable)

4. **`scenes`** ([`2026_10_09_200004_create_scenes_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200004_create_scenes_table.php)):
   - `id` (uuid, pk)
   - `project_id` (foreignUuid -> projects.id, cascade on delete)
   - `scene_order` (integer)
   - `slugline` (string, ej: "EXT. BAR - NOCHE 1")
   - `location_id` (foreignUuid -> locations.id, nullable, null on delete)
   - `time_of_day` (string)

5. **`script_blocks`** ([`2026_10_09_200005_create_script_blocks_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200005_create_script_blocks_table.php)):
   - `id` (uuid, pk)
   - `scene_id` (foreignUuid -> scenes.id, cascade on delete)
   - `block_order` (integer)
   - `raw_text` (text)
   - `structured_text` (text, nullable)
   - `screenplay_content` (text, nullable)
   - `user_locks` (jsonb, default '{}')
   - `stage` (enum: 'raw', 'structured', 'screenplay', 'shots', 'editing', default 'raw')

6. **`shots`** ([`2026_10_09_200006_create_shots_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200006_create_shots_table.php)):
   - `id` (uuid, pk)
   - `scene_id` (foreignUuid -> scenes.id, cascade on delete)
   - `script_block_id` (foreignUuid -> script_blocks.id, nullable, null on delete)
   - `shot_number` (integer)
   - `shot_type` (string, ej: "PM", "PG", "PP", "PD")
   - `description` (text)

7. **`takes`** ([`2026_10_09_200007_create_takes_table.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/migrations/2026_10_09_200007_create_takes_table.php)):
   - `id` (uuid, pk)
   - `shot_id` (foreignUuid -> shots.id, cascade on delete)
   - `take_number` (integer)
   - `file_path` (string, nullable)
   - `is_good_take` (boolean, default false)
   - `editorial_notes` (text, nullable)
   - `technical_meta` (jsonb, nullable)

### Paso 4: Implementación de Modelos Eloquent (`app/Models/`)
Cada modelo incorpora `HasUuids`, `$fillable` exhaustivo, `$casts` tipados y las relaciones correspondientes:
- [`User.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/User.php): `hasMany(Project::class)`
- [`Project.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Project.php): `belongsTo(User::class)`, `hasMany(Character::class)`, `hasMany(Location::class)`, `hasMany(Scene::class)` ordenado por `scene_order`.
- [`Character.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Character.php): `belongsTo(Project::class)`, cast `'custom_attributes' => 'array'`.
- [`Location.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Location.php): `belongsTo(Project::class)`, `hasMany(Scene::class)`.
- [`Scene.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Scene.php): `belongsTo(Project::class)`, `belongsTo(Location::class)`, `hasMany(ScriptBlock::class)` ordenado por `block_order`, `hasMany(Shot::class)` ordenado por `shot_number`.
- [`ScriptBlock.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/ScriptBlock.php): `belongsTo(Scene::class)`, `hasMany(Shot::class)`, cast `'user_locks' => 'array'`, default `'user_locks' => '{}'`.
- [`Shot.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Shot.php): `belongsTo(Scene::class)`, `belongsTo(ScriptBlock::class)`, `hasMany(Take::class)` ordenado por `take_number`.
- [`Take.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Take.php): `belongsTo(Shot::class)`, casts `'is_good_take' => 'boolean'`, `'technical_meta' => 'array'`.

### Paso 5: Factories y Seeders
- **Factories Creadas:**
  - `UserFactory.php`, `ProjectFactory.php`, `CharacterFactory.php`, `LocationFactory.php`, `SceneFactory.php`, `ScriptBlockFactory.php`, `ShotFactory.php`, `TakeFactory.php`.
- **`DemoProjectSeeder.php`** ([`database/seeders/DemoProjectSeeder.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/database/seeders/DemoProjectSeeder.php)):
  Siembra una jerarquía completa de prueba:
  1. Usuario: `Javier Gálvez` (`filmmaker@cabocom.net`, plan `pro`).
  2. Proyecto: *"El Susurro del Viento"*.
  3. Personaje en biblia: *"Javier"* (arquetipo Protagonista, psicología, conflicto interno).
  4. Localización en biblia: *"BAR CENTRAL"* (`INT`, detalles de arte e iluminación).
  5. Escena: `1`, slugline *"INT. BAR CENTRAL - NOCHE 1"*.
  6. Bloque de guión: `raw_text`, `structured_text`, `screenplay_content` en formato cinematográfico, y `user_locks` con diff manual y término fijado (`Javi -> Javier`).
  7. Plano: `1`, tipo `PM` (Plano Medio).
  8. Tomas:
     - Toma 1: `is_good_take = true`, con `technical_meta` (4K ProRes 422 HQ, 24fps, timecode).
     - Toma 2: `is_good_take = false`, con nota editorial de descarte por micrófono de pértiga visible.
- **`DatabaseSeeder.php`** configurado para invocar a `DemoProjectSeeder`.

### Paso 6: Suite de Pruebas de Arquitectura
Se implementó [`tests/Feature/DomainModelArchitectureTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/DomainModelArchitectureTest.php) con 4 casos de prueba automatizados:
1. `test_all_domain_entities_generate_uuidv4_primary_keys`: comprueba mediante `Str::isUuid()` que todas las entidades generan identificadores válidos UUIDv4.
2. `test_domain_relationships_and_navigation`: valida la navegación bidireccional entre todas las relaciones Eloquent del dominio.
3. `test_user_locks_and_technical_meta_json_casts`: verifica que las estructuras JSONB persisten y se deserializan como arrays nativos en PHP.
4. `test_cascading_deletes_when_project_is_removed`: verifica que al eliminar un proyecto, se eliminan en cascada personajes, localizaciones, escenas, bloques, planos y tomas.

### Paso 7: Verificación de Formateo y Linters
Se ejecutó `Laravel Pint` para corregir y normalizar el estilo de código del proyecto bajo los estándares PSR-12 / Laravel:
```bash
./vendor/bin/pint
# Salida: 3 archivos normalizados (ShotFactory, LocationFactory, SceneFactory).
./vendor/bin/pint --test
# Salida: {"tool":"pint","result":"passed"}
```

---

## 4. Resultados de Verificación

### 4.1. Migraciones de Base de Datos
```bash
php artisan migrate
```
```text
INFO Running migrations.

0001_01_01_000000_create_users_table ......... 4.00ms DONE
0001_01_01_000001_create_cache_table ......... 2.12ms DONE
0001_01_01_000002_create_jobs_table .......... 2.52ms DONE
2026_10_09_200001_create_projects_table ...... 1.59ms DONE
2026_10_09_200002_create_characters_table .... 1.11ms DONE
2026_10_09_200003_create_locations_table ..... 0.77ms DONE
2026_10_09_200004_create_scenes_table ........ 1.37ms DONE
2026_10_09_200005_create_script_blocks_table . 1.33ms DONE
2026_10_09_200006_create_shots_table ......... 1.53ms DONE
2026_10_09_200007_create_takes_table ......... 1.30ms DONE
```

### 4.2. Ejecución del Seeder
```bash
php artisan db:seed
```
```text
INFO Seeding database.

Database\Seeders\DemoProjectSeeder .. RUNNING
Database\Seeders\DemoProjectSeeder .. 223 ms DONE
```

### 4.3. Ejecución de Pruebas Automatizadas
```bash
php artisan test
```
```json
{
  "tool": "phpunit",
  "result": "passed",
  "tests": 6,
  "passed": 6,
  "assertions": 41,
  "duration_ms": 167
}
```

---

## 5. Próximos Pasos (Fase 2)

Con la base de persistencia y modelos consolidada, el siguiente incremento del desarrollo abordará:
1. **Pipeline de Transformación:** Servicios para transicionar `ScriptBlock` entre estados (`raw` -> `structured` -> `screenplay` -> `shots`).
2. **Motor de Retención de Bloqueos (`UserLockResolver`):** Lógica para fusionar texto estructurado y guión preservando los `user_locks` del autor ante re-ejecuciones de IA.
3. **Conector HTTP HMAC-SHA256:** Cliente HTTP tipado para invocar a `jg_agente` consumiendo las herramientas de `jg_plugin_filmmaker` respetando las firmas `X-Signature` y la trazabilidad con `X-Correlation-ID`.
