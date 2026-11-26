# Issue: Categorías de Proyecto (`Videos IA` / `Proyectos personales`) y API REST `/api/v1/projects`

- **Fase:** Fase 1 — Fundación de Proyectos Vídeos IA
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 4 – 5 h (Media)
- **Estado:** Cerrado (Commit `610da00`, GitLab `jg_filmmaker#38`)

## Contexto y Motivación

Actualmente `jg_filmmaker` no distingue entre proyectos cinematográficos manuales y proyectos generados automáticamente desde escaletas de televisión / flujos de IA, ni dispone de una API REST canónica `/api/v1/projects` para crear y consultar proyectos desde servicios del ecosistema (`jg_agente_tv`, `jg_hub` / AI Today).

## Alcance Técnico (SOLID)

1. **Categorías de Proyecto (`ProjectCategory` Enum + Migración):**
   - Añadir columna `category` (`string`, indexada, default `'personal'`) en la tabla `projects` con Enum `App\Enums\ProjectCategory`:
     - `PERSONAL = 'personal'` (*"Proyectos personales"*)
     - `AI_VIDEO = 'ai_video'` (*"Videos IA"*)
   - Actualizar `Project::$fillable` y casts (`category => ProjectCategory::class`), manteniendo 100% de compatibilidad con proyectos existentes (migrados automáticamente a `'personal'`).
2. **Acción SOLID de Creación (`App\Actions\Projects\CreateProjectAction`):**
   - Encapsular la creación de proyectos (`Project` + escena inicial opcional + `ScriptBlock` inicial si se aporta `raw_text`) con validación de cuota de slots **solo** para proyectos `personal` (sin bloquear la producción automatizada de `ai_video`).
   - Hacer que `AutoGenerateBroadcastShotsAction::initialize()` delegue en esta lógica creando siempre proyectos con `category = ProjectCategory::AI_VIDEO`.
3. **API REST Canónica M2M (`/api/v1/projects`):**
   - Crear `App\Http\Controllers\Api\V1\ProjectController` + `StoreProjectApiRequest` + `UpdateProjectApiRequest` protegidos con `['hmac.m2m', 'throttle:60,1']`:
     - `GET /api/v1/projects` (con filtro opcional `?category=ai_video|personal`).
     - `POST /api/v1/projects` (crea proyecto categorizado, soporta `title`, `synopsis`, `category`, `storyboard_style`, `raw_text`, `slugline`).
     - `GET /api/v1/projects/{project}` y `PATCH /api/v1/projects/{project}`.
4. **UI de `ProjectList` con Pestañas por Categoría:**
   - Añadir pestañas/filtro en `ProjectList` (*"Todos"*, *"Proyectos personales"*, *"Videos IA"*) y badge visual de categoría en cada tarjeta, permitiendo también seleccionar la categoría al crear un proyecto manualmente.
5. **Tests y Documentación:**
   - Tests PHPUnit para el Enum, `CreateProjectAction`, endpoints `/api/v1/projects` (HMAC, validación, filtros, 404/422), `AutoGenerateBroadcastShotsAction` (`category = ai_video`) y filtrado en `ProjectList`.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
