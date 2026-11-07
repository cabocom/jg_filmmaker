# Walkthrough: Generación de Storyboard con Google AI Studio y Referencia de Personaje

## Resumen Ejecutivo

Se ha implementado con éxito la funcionalidad completa de generación de viñetas de **Storyboard** para cada plano técnico (`Shot`) en `jg_filmmaker`, integrando:
1. **Google AI Studio (Imagen 3 / Gemini API)** como motor de renderizado gráfico de alta fidelidad 16:9.
2. **Condicionamiento de personaje** mediante hoja de modelo / turn-around (`Character.reference_image_path`).
3. **Catálogo de 3 estilos visuales seleccionables**:
   - `grayscale_sketch` (Escala de Grises / Carboncillo de producción)
   - `kids_2d_animation` (Animación 2D Infantil / Cel-shading)
   - `enki_bilal` (Novela Gráfica / Sci-Fi Noir distópico)
4. **Gestión integral en la UI (Livewire)**:
   - Carga y preview de imagen de referencia en la Biblia del Proyecto (`ContextBibleDrawer`).
   - Selector de estilo de storyboard en la etapa de Planos de `ScriptEditor`.
   - Generación / regeneración por plano con feedback de carga, renderizado de viñeta y modal lightbox a pantalla completa.

---

## Diagrama de Arquitectura y Flujo de Datos

```text
+-----------------------------------------------------------------------------------------+
|                                     USUARIO / UI                                        |
+-----------------------------------------------------------------------------------------+
       |                                                               |
       v                                                               v
[ContextBibleDrawer]                                           [ScriptEditor (Tab Planos)]
- Sube Turn-Around (JPG/PNG)                                   - Selector de Estilo (3 estilos)
- Asocia a Character (Bio/Costume)                             - Botón "Generar Storyboard"
       |                                                               |
       v                                                               v
[Character.reference_image_path]                               [generateShotStoryboard(shotId)]
       |                                                               |
       +-------------------------------+-------------------------------+
                                       |
                                       v
                           [StoryboardService]
       +---------------------------------------------------------------+
       | 1. buildPrompt(Shot, Style):                                  |
       |    - Encuadre técnico (Shot.shot_type)                        |
       |    - Descripción de la acción (Shot.description)              |
       |    - Escena (Scene.slugline & scene_order)                    |
       |    - Condicionamiento visual Character (Turn-around + Costume)|
       |    - Modificador estilístico (Grayscale / 2D / Bilal)         |
       | 2. Google AI Studio API (Imagen 3):                           |
       |    - POST /v1beta/models/imagen-3.0-generate-002:predict      |
       |    - Aspect Ratio 16:9, output MIME image/jpeg                |
       | 3. Storage & Persistencia:                                    |
       |    - storage/app/public/projects/{id}/storyboards/*.jpg       |
       |    - Shot.storyboard_image_path & storyboard_style            |
       +---------------------------------------------------------------+
                                       |
                                       v
                 [Shot Card & Lightbox Preview en UI]
```

---

## Cambios Implementados por Fase

### Fase 1: Modelo de Datos y Soporte de Imagen de Referencia
- **Migración** (`database/migrations/2026_11_07_220000_add_storyboard_and_character_reference_columns.php`):
  - `characters.reference_image_path`: Ruta relativa a la hoja de modelo del personaje.
  - `shots.storyboard_image_path`: Ruta a la viñeta JPEG generada.
  - `shots.storyboard_style`: Identificador del estilo utilizado (`grayscale_sketch`, etc.).
  - `shots.storyboard_prompt`: Prompt exacto enviado al motor de IA.
  - `projects.storyboard_style`: Estilo por defecto del proyecto.
- **Modelos**:
  - `App\Models\Character`: Atributos fillable y helpers `hasReferenceImage()`, `reference_image_url`.
  - `App\Models\Shot`: Helpers `hasStoryboard()`, `storyboard_url`.
  - `App\Models\Project`: Fillable `storyboard_style`.
- **Biblia de Proyecto (`ContextBibleDrawer`)**:
  - Soporte de subida y eliminación de hoja de diseño turn-around para personajes.
  - Previsualizaciones interactivas con enlace a zoom.
  - Actualización de `config/livewire.php` para habilitar subida de extensiones gráficas (`png,jpg,jpeg,webp`) en el pipeline temporal.

### Fase 2: Motor `StoryboardService` y Google AI Studio
- **Configuración** (`config/services.php`):
  - Registro de `google_ai` (`api_key`, `model`, `base_url`, `timeout`).
- **Servicio** (`app/Services/StoryboardService.php`):
  - Catálogo canónico de estilos (`STYLES`):
    * `grayscale_sketch`: Producción cinematográfica a carboncillo y sombras noir.
    * `kids_2d_animation`: Cel-shading vectorial 2D, colores vivos y saturados.
    * `enki_bilal`: Novela gráfica europea, colores apagados azul/ocre y texturas al pastel.
  - `buildPrompt(Shot $shot, ?string $style)`: Ensamblaje contextual con condicionamiento de personaje.
  - `generateStoryboard(Shot $shot, ?string $style)`: Conexión con Imagen 3, decodificación base64, guardado en disco y limpieza de viñetas obsoletas. Soporte de viñeta de reserva para entornos de prueba offline.

### Fase 3: Interfaz de Usuario en `ScriptEditor`
- **Componente Livewire** (`app/Livewire/Studio/ScriptEditor.php`):
  - Propiedad y selector `$storyboardStyle` con persistencia en el proyecto.
  - Acción `generateShotStoryboard($shotId)` con captura y notificación de errores.
  - Modal de lightbox a pantalla completa (`openStoryboardPreview` / `closeStoryboardPreview`).
- **Vista Blade** (`resources/views/livewire/studio/script-editor.blade.php`):
  - Barra de selección de estilos con indicador de personajes con turn-around activo.
  - Tarjetas de plano con viñeta 16:9, botón de regenerar, distintivo del estilo y lightbox.
  - Previsualización en miniatura integrada en la lectura continua de escenas.

### Fase 4: Pruebas Automatizadas y Calidad
- **Feature Tests** (`tests/Feature/StoryboardGenerationTest.php`):
  1. `test_storyboard_service_catalog_contains_the_three_required_styles`: Verificación del catálogo.
  2. `test_storyboard_service_builds_prompt_with_correct_modifiers_for_each_style`: Modificadores por estilo.
  3. `test_storyboard_service_conditions_prompt_when_character_has_reference_turnaround`: Condicionamiento del personaje.
  4. `test_storyboard_service_generates_image_from_google_ai_studio_api`: Mock de Google AI Studio con `Http::fake()`.
  5. `test_script_editor_can_switch_storyboard_style_and_generate_from_livewire`: Interacción completa en `ScriptEditor`.
  6. `test_context_bible_drawer_supports_character_reference_image_upload_and_delete`: Ciclo de vida de imagen en `ContextBibleDrawer`.
- **Resultados de Calidad**:
  - `php artisan test`: 219 tests pasados, 0 fallos (100%).
  - `./vendor/bin/pint --test`: 100% de cumplimiento con los estándares de estilo Laravel/Pint.

---

## Verificación Manual Recomendada

1. Navegar a un proyecto en el estudio.
2. Abrir la **Biblia del Proyecto** (`ContextBibleDrawer`), añadir o editar un personaje y subir una hoja de modelo turn-around (ej. frontal, perfil y espaldas).
3. Ir a la pestaña **4. Planos** en el editor o en el cajón de detalle de un bloque.
4. Seleccionar uno de los 3 estilos (`Escala de Grises`, `Animación 2D Infantil` o `Enki Bilal`).
5. Pulsar en **Generar Storyboard (IA)** sobre cualquier plano técnico.
6. Comprobar la aparición de la viñeta 16:9, la etiqueta de estilo y pulsar el icono de zoom para ver el modal lightbox.

