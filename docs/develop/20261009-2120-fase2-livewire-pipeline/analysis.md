# Análisis Técnico: Fase 2 — Interfaz Reactiva Livewire 3, Pipeline Visual y Drawer Lateral

**Proyecto:** `jg_filmmaker`  
**Ruta:** `docs/develop/20261009-2120-fase2-livewire-pipeline/analysis.md`  
**Fecha:** 9 de octubre de 2026, 21:20 CET  
**Autor:** Antigravity (Ingeniero Full-Stack Senior)  
**Estado:** Análisis Consolidado y Aprobado  

---

## 1. Prompt Recibido

```markdown
[PROJECT: jg_filmmaker] - Fase 2: Interfaz Reactiva Livewire 3, Pipeline Visual y Drawer Lateral

Actúa como Ingeniero Full-Stack Senior especializado en Laravel 12, Livewire 3 y Tailwind CSS dentro del ecosistema jg_ia (con la misma línea técnica aplicada en jg_hub).

Hemos completado con éxito la Fase 1 (arquitectura de datos con UUIDs, modelos Eloquent, migraciones y seeders). Ahora vamos a implementar el enfoque visual primero para poder navegar, probar e interactuar con la aplicación cinematográfica desde el navegador.

### CONTEXTO Y ESPECIFICACIONES:
- docs/specs/01_architecture_and_models.md
- docs/specs/02_pipeline_stages_and_locks.md
- docs/specs/04_shots_takes_media_management.md
- Base de datos actual: database/database.sqlite ya poblada con DemoProjectSeeder (Proyecto "El Susurro del Viento", personaje "Javier", localización "BAR CENTRAL", Escena 1 con bloques, planos y tomas).

### DIRECTRICES TÉCNICAS OBLIGATORIAS:
1. Stack Frontend:
   - Instalar y configurar Livewire v3 (`composer require livewire/livewire`).
   - Usar Tailwind CSS (mediante Vite o bundle integrado de Laravel) para un diseño cinematográfico oscuro/limpio (tema estilo Dark Studio o Slate moderno).
   - Utilizar componentes Livewire 3 reactivos sin recargas de página completas.

2. Enrutamiento y Resolución por UUID:
   - Las rutas deben resolver exclusivamente mediante UUID (`Route::get('/projects/{project}', ...)`).
   - Proteger los endpoints con la política de usuario (`auth()->id()` o emulado con el usuario demo de DemoProjectSeeder para desarrollo local).

3. Vistas y Componentes a Implementar:
   A. Layout Maestro de Estudio (`resources/views/layouts/app.blade.php`)
   B. Dashboard de Proyectos (`ProjectList` - Livewire)
   C. Editor de Estudio y Pipeline (`ScriptEditor` - Livewire)
   D. Visor de Desglose de Planos y Tomas (`ShotTakesViewer` o sección integrada)
   E. Drawer Lateral de la Biblia Contextual (`ContextBibleDrawer` - Livewire)

### OBJETIVO DE ESTE PASO:
1. Instala y configura Livewire 3 en jg_filmmaker.
2. Crea los componentes Livewire y vistas Blade correspondientes.
3. Configura las rutas en `routes/web.php` permitiendo autenticación automática o de desarrollo con el usuario de `DemoProjectSeeder`.
4. Añade al menos 2 tests de interfaz con PHPUnit/Pest para comprobar que el dashboard y el editor de escenas renderizan correctamente los UUIDs y los datos del proyecto demo.
5. Ejecuta `php artisan test` y `vendor/bin/pint` para validar que todo queda limpio y sin errores.
```

---

## 2. Análisis de Requisitos y Arquitectura

### 2.1. Experiencia de Usuario y Estética Cinematográfica (Aesthetics)
- **Tema:** Dark Studio / Slate cinematográfico (`#020617` / `#0f172a`), superficies con elevación y bordes sutiles en `#1e293b` y `#334155`.
- **Acentos:** Dorado / Ámbar cálido (`amber-500` / `amber-400`) para simular la iluminación clásica de claqueta y celuloide, junto a esmeralda (`emerald-500`) para tomas buenas / aprobadas de montaje.
- **Tipografía de Guión:** Integración de Google Fonts con **Courier Prime** (el estándar de la industria cinematográfica para guiones a 12pt) combinado con **Inter** para la interfaz moderna de estudio.

### 2.2. Reactividad sin Recarga de Página (Livewire 3)
- Toda la interacción dramática y técnica (cambio de escenas, edición de bloques, conmutación de etapas del pipeline, aprobación de tomas y apertura de la biblia) debe operar reactivamente vía Livewire 3 sin recargar el navegador.
- Enfoque de componentes modulares desacoplados:
  1. `ProjectList`: control de proyectos y cuotas de usuario (`paid_project_slots`).
  2. `ScriptEditor`: orquestador de escena, visualizador del pipeline (Bruto -> Estructurado -> Guión Literario) y visor de planos y tomas.
  3. `ContextBibleDrawer`: panel deslizante con escucha de eventos globales (`#[On('toggle-bible-drawer')]`, `Cmd+B`) para consultar y editar personajes y escenarios sin perder el foco del guión.

### 2.3. Resolución Segura por UUID y Autenticación de Desarrollo
- **Resolución Eloquent implícita:** `{project}` enrutado mediante UUIDv4 natively soportado gracias a `HasUuids`.
- **Middleware `EnsureDemoUserAuthenticated`:** Middleware transparente que verifica si el usuario está autenticado. Si no lo está (durante desarrollo local en `localhost:8000`), autentica de inmediato a `filmmaker@cabocom.net` (usuario de `DemoProjectSeeder`), garantizando que `auth()->user()` siempre exista sin forzar formularios de login que bloqueen la agilidad del desarrollo.

### 2.4. Representación del Pipeline y Retención de Cambios (`user_locks`)
- En el `ScriptEditor`, cada bloque debe manifestar claramente:
  * Si contiene candados activos (`user_locks['pinned_terms']` o `locked_fields`).
  * En caso afirmativo, un indicador visual con icono 🔒 y badges con los términos fijos (ej: `Javi ➜ Javier`).
  * Conmutador de vistas (`viewMode`): permite alternar entre ver el pipeline en columnas paralelas o enfocarse exclusivamente en el Guión Literario Courier o en el Desglose de Planos.

### 2.5. Gestión Técnica de Tomas
- Implementación del botón interactivo para conmutar `is_good_take` en un solo clic, permitiendo al director o montador clasificar el material rodado en tiempo real.
