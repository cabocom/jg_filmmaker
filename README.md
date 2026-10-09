# jg_filmmaker

**jg_filmmaker** es una aplicación web desacoplada en Laravel 12 concebida para la gestión integral y asistencia con Inteligencia Artificial del ciclo completo de producción cinematográfica (Idea -> Estructura -> Guión -> Rodaje -> Edición -> Montaje).

Forma parte del ecosistema multi-servicio `javiergalvez-ia`, comunicándose de manera asíncrona mediante peticiones HTTP firmadas con **HMAC-SHA256** con el runtime cognitivo `jg_agente` a través del plugin especializado `jg_plugin_filmmaker`.

---

## Características de la Fase 1: Arquitectura Base y Multitenancy

1. **Multitenancy y Claves UUIDv4:**
   - Todas las entidades de dominio utilizan exclusivamente identificadores UUIDv4 (`$table->uuid('id')->primary()`).
   - Prevención estricta de ataques IDOR; no se exponen IDs numéricos secuenciales en rutas ni endpoints.
   - Todos los modelos Eloquent implementan el trait `Illuminate\Database\Eloquent\Concerns\HasUuids`.
   - Eliminación en cascada garantizada mediante claves foráneas tipadas con `foreignUuid()`.

2. **Entidades de Dominio Implementadas:**
   - **`User`**: Soporte de planes (`plan_tier`: free/pro) y cuotas (`paid_project_slots`).
   - **`Project`**: Contenedor principal de producción con estado (`draft`, `in_progress`, `completed`).
   - **`Character`**: Biblia contextual de personajes (arquetipo, psicología, vestuario y atributos JSON).
   - **`Location`**: Biblia de localizaciones (`INT`, `EXT`, `INT/EXT`, iluminación y referencias de arte).
   - **`Scene`**: Secuencias numeradas con orden narrativo, sluglines cinematográficos y asociación de localizaciones.
   - **`ScriptBlock`**: Núcleo de transformación con retención de modificaciones manuales mediante `user_locks` (JSONB) y etapas de pipeline (`raw`, `structured`, `screenplay`, `shots`, `editing`).
   - **`Shot`**: Desglose técnico de planos cinematográficos (`PG`, `PM`, `PP`, `PD`) vinculables a bloques de guión.
   - **`Take`**: Tomas de rodaje con catalogación editorial (`is_good_take`, `editorial_notes`) y metadatos técnicos de vídeo (`technical_meta`: duración, resolución, codec, fps).

3. **Testing y Fiabilidad:**
   - Suite de pruebas automatizadas con PHPUnit / Laravel Test suite (`tests/Feature/DomainModelArchitectureTest.php`).
   - Seeders y Factories para inicialización rápida de entornos de desarrollo (`DemoProjectSeeder`).
   - Formateo estricto conforme a estándares con Laravel Pint.

---

## Ejecución Local y Pruebas

```bash
# Instalar dependencias
composer install

# Configurar variables de entorno
cp .env.example .env
php artisan key:generate

# Ejecutar migraciones y sembrado de prueba
php artisan migrate:fresh --seed

# Ejecutar suite de pruebas
php artisan test

# Formatear código con Pint
./vendor/bin/pint
```
