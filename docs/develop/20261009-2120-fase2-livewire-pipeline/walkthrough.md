# Walkthrough Técnico: Fase 2 — Interfaz Reactiva Livewire 3, Pipeline Visual y Drawer Lateral

**Proyecto:** `jg_filmmaker`  
**Ruta:** `docs/develop/20261009-2120-fase2-livewire-pipeline/walkthrough.md`  
**Fecha:** 9 de octubre de 2026, 21:20 CET  
**Versión:** 1.1.0  
**Stack:** Laravel 12 (`v12.69.3`), Livewire (`v4.4.7`), Tailwind CSS (`v4.0.0`), Vite 8, PHPUnit 12, Pint  

---

## 1. Resumen de Archivos Creados y Modificados

### 1.1. Componentes Livewire y Middleware (`app/`)
- [`app/Http/Middleware/EnsureDemoUserAuthenticated.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Http/Middleware/EnsureDemoUserAuthenticated.php): Middleware de auto-login transparente con el usuario demo de `DemoProjectSeeder` (`filmmaker@cabocom.net`, plan PRO). Protegido contra tablas inexistentes en tests unitarios tempranos.
- [`app/Livewire/Projects/ProjectList.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Projects/ProjectList.php): Componente Livewire del dashboard de proyectos cinematográficos, con cálculo reactivo de slots utilizados y modal de creación validado contra cuotas.
- [`app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php): Componente principal del estudio cinematográfico. Gestiona navegación por escenas, conmutador de modos de vista del pipeline (`all`, `screenplay`, `structured`, `shots`), edición inline de bloques, transiciones de etapa (`raw` -> `structured` -> `screenplay`), toggle de tomas válidas y alta rápida de planos técnicos y tomas.
- [`app/Livewire/Studio/ContextBibleDrawer.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php): Panel deslizante derecho que escucha eventos Livewire (`toggle-bible-drawer`) y atajo de teclado global (`Cmd+B` / `Ctrl+B`). Permite consultar y modificar notas de arte, iluminación, vestuario y biografía en tiempo real.

### 1.2. Plantillas Blade y Estilos (`resources/views/`)
- [`resources/views/layouts/app.blade.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/layouts/app.blade.php): Layout maestro de estudio cinematográfico oscuro (Dark Studio) con tipografías Google Fonts (Inter + Courier Prime), barra superior con badges de plan PRO, selector de proyecto, contador de slots, botón de biblia y script de atajo `Cmd+B`.
- [`resources/views/livewire/projects/project-list.blade.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/projects/project-list.blade.php): Vista con grid de tarjetas de proyectos, contadores de escenas/personajes/localizaciones, identificadores UUID truncados y modal de creación de proyectos.
- [`resources/views/livewire/studio/script-editor.blade.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/script-editor.blade.php): Vista reactiva del guión y pipeline. Muestra slugline cinematográfico, tarjetas de bloques con candados visuales 🔒 (`user_locks`), editor tipográfico de guión en formato estándar Courier, desglose de planos canónicos (`--- shot N (tipo) ---`), tomas con insignias de toma buena y metadatos técnicos (4K ProRes, 24fps).
- [`resources/views/livewire/studio/context-bible-drawer.blade.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/context-bible-drawer.blade.php): Vista deslizante con pestañas de Personajes y Localizaciones con edición inline reactiva.

### 1.3. Enrutamiento y Configuración
- [`routes/web.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/routes/web.php): Rutas configuradas con resolución estricta por UUID:
  - `GET /` -> Redirección a `/projects`.
  - `GET /projects` -> `ProjectList::class` (`projects.index`).
  - `GET /projects/{project}` -> `ScriptEditor::class` (`projects.show`).
- [`bootstrap/app.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/bootstrap/app.php): Registro del middleware `EnsureDemoUserAuthenticated` en el grupo `web`.
- [`package.json`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/package.json) / Vite: Compilación exitosa de bundle con Tailwind CSS.

### 1.4. Suite de Pruebas de Interfaz (`tests/Feature/`)
- [`tests/Feature/LivewireProjectDashboardTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/LivewireProjectDashboardTest.php): Verifica renderizado de `/projects`, visualización de datos sin fugas de IDs enteros y creación de proyectos reactiva mediante Livewire.
- [`tests/Feature/LivewireScriptEditorTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/LivewireScriptEditorTest.php): Verifica renderizado del slugline de escena, bloques de guión con indicadores de candado 🔒 (`user_locks`), desglose técnico de planos y tomas, acción interactiva de alternar toma buena (`toggleGoodTake`), y edición reactiva dentro del `ContextBibleDrawer`.
- [`tests/Feature/ExampleTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/ExampleTest.php): Adaptado para verificar la redirección a `projects.index` con `RefreshDatabase`.

---

## 2. Walkthrough de Ejecución y Validaciones

### 2.1. Instalación de Dependencias
```bash
composer require livewire/livewire
npm install
npm run build
```
Vite generó los assets optimizados en `public/build/assets/app-BD8p6KZa.css` (76 kB) con soporte para las clases de diseño cinematográfico.

### 2.2. Migraciones y Base de Datos
La base de datos SQLite de desarrollo quedó poblada con el seeder `DemoProjectSeeder`:
```bash
php artisan db:seed
# Output: Database\Seeders\DemoProjectSeeder .. 205 ms DONE
```

### 2.3. Linters y Formateo de Código (Laravel Pint)
```bash
./vendor/bin/pint --test
```
```text
{"tool":"pint","result":"passed"}
```

### 2.4. Suite de Pruebas Automatizadas
```bash
php artisan test
```
```json
{
  "tool": "phpunit",
  "result": "passed",
  "tests": 11,
  "passed": 11,
  "assertions": 61,
  "duration_ms": 267
}
```
**Resultado:** 11 pruebas pasando al 100% (6 de arquitectura de datos + 5 de interfaz Livewire).

---

## 3. Instrucciones de Ejecución Local para Navegador

Para levantar y explorar la aplicación interactivamente:

1. **Terminal 1 — Servidor de Aplicación Laravel:**
   ```bash
   cd /Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker
   php artisan serve
   ```
   *Acceso web:* **`http://localhost:8000`** (o `http://127.0.0.1:8000`)
   - El sistema te autenticará de forma automática con el usuario demo `filmmaker@cabocom.net` (Plan PRO, 5 slots).
   - Accederás directamente al **Dashboard de Proyectos**, donde verás *"El Susurro del Viento"*.
   - Pulsa en **"Entrar al Estudio"** para acceder a la vista interactiva `/projects/{uuid}`.

2. **Terminal 2 — Servidor Vite (Opcional para HMR):**
   ```bash
   cd /Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker
   npm run dev
   ```
   *(Los assets de producción ya han sido precompilados con `npm run build`, por lo que el paso con `npm run dev` solo es necesario si deseas Hot-Module Replacement en tiempo real).*

3. **Atajos de Teclado y Pruebas en el Estudio:**
   - En el editor de escena, pulsa **`Cmd+B`** (en Mac) o **`Ctrl+B`** (en Linux/Windows) o pulsa el botón **"📖 Biblia de Proyecto"** en la barra superior para abrir el Drawer deslizante.
   - Alterna entre los tabs de la barra del pipeline: **"Pipeline"**, **"Guión"** (formato Courier cinematográfico), **"Tratamiento"** o **"Planos / Tomas"**.
   - Haz clic sobre el botón de **"TOMA BUENA" / "Descarte"** en cualquiera de las tomas para observar la reactividad inmediata sin parpadeos ni recargas de página.
