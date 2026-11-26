# Issue: Sección configurable de Estilos Visuales (`🎨 Estilos`) y selección por Escaleta IA

- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 4 – 5 h (Media)
- **Estado:** Cerrado (commit `67df4b6`)

## Contexto y Motivación

Actualmente las imágenes generadas en Escaletas IA caen por defecto en `'editorial_documentary_35mm'` (estética fotorrealista), lo que puede generar problemas de derechos de autor o no encajar con la línea gráfica deseada (storyboard ilustrado, animación 2D, etc.). Es necesario poder definir estilos visuales propios (con prompt base, características e imagen de referencia opcional) y elegir qué estilo utiliza cada escaleta.

## Alcance Técnico (SOLID)

1. **Modelo y Migración `TvVisualStyle`:**
   - Tabla `tv_visual_styles` (`id` UUID, `name`, `slug` unique, `prompt_base`, `characteristics`, `reference_image_path` nullable, `is_default`, `is_active`, timestamps).
   - Añadir `visual_style_id` (nullable FK a `tv_visual_styles`) en `tv_rundowns` y `projects`.
   - Seed/inicialización automática de 2 estilos por defecto no fotorrealistas: **Storyboard Clásico** y **Animación 2D** (ambos con directivas anti-fotorrealismo explícitas).
2. **Acción `ManageTvVisualStyleAction` y Pestaña `🎨 Estilos` (`App\Livewire\Tv\TvStylesPanel`):**
   - CRUD completo en `/tv` (pestaña `Estilos`) con subida de imagen de referencia (`WithFileUploads`), edición de prompt base y características, activación/desactivación y marcado de estilo por defecto.
3. **Integración en Escaletas IA y Generación Visual:**
   - Selector de Estilo Visual en `TvRundownsPanel` (creación y edición de escaleta) que sincroniza `visual_style_id` y `visual_aesthetic_config` con el `Project` vinculado.
   - Propagar el `prompt_base`, `characteristics` y `reference_image_path` (como `styleReferenceImageBase64` en plano #1 cuando no hay ancla previa) desde `StoryboardShotVisual` hacia `StoryboardService` / `VisualAestheticConfigDTO`.
4. **Tests:**
   - Tests PHPUnit del CRUD de `TvVisualStyle`, selección en `TvRundownsPanel` y aplicación del prompt/referencia del estilo en `StoryboardShotVisual`.
