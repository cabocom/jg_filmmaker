# Issue: Control Estético Avanzado y Banco de Pruebas Visuales en `Shots` del Estudio

- **Fase:** Fase 2 — Control Estético 16:9 Full-Bleed y Banco de Pruebas en Shots
- **Proyecto:** `jg_filmmaker` (`javiergalvez-ia/jg_filmmaker`)
- **Asignado:** Javier Gálvez (`cabocom`)
- **Esfuerzo estimado:** 5 – 6 h (Media)
- **Estado:** Cerrado (Commit `21f053e`, GitLab `jg_filmmaker#40`)

## Contexto y Motivación

Para no dejar la estética de los vídeos a criterio libre de la IA ni esperar al renderizado completo del episodio para descubrir cómo lucen las imágenes, el usuario necesita controlar desde cada `Shot` de `jg_filmmaker` el preset artístico, la paleta de colores, la iluminación, el modo *16:9 full-bleed sin texto* y la vinculación con la Biblia, pudiendo lanzar pruebas iterativas hasta aprobar cada imagen antes de generar el vídeo final.

## Alcance Técnico (SOLID)

1. **Catálogo de Presets Estéticos y `BroadcastFramePromptBuilder`:**
   - Crear `App\DTOs\Storyboard\VisualAestheticConfigDTO` y `App\Services\Storyboard\BroadcastFramePromptBuilder` (sin engordar `StoryboardPromptBuilder` ni `StoryboardService`).
   - Soportar presets editoriales full-bleed (Fotografía Documental 35mm, Ilustración Editorial Limpia, Sci-Fi Pictórico, Minimalista Tech) + campos personalizables (`color_palette`, `lighting_style`, `camera_lens`, `render_mode: full_bleed_frame | storyboard_sketch`).
   - Persistir la configuración estética por proyecto (`visual_aesthetic_config` JSON) y override opcional por `Shot`.
2. **Banco de Pruebas Interactivo en el Modal de `Shot`:**
   - Ampliar el modal visual de `Shot` (delegando en `ManageStoryboardAction`) para configurar paleta, iluminación, modo limpio 16:9, Personaje y Localización de la Biblia, y lanzar regeneraciones rápidas de prueba por plano.
   - Garantizar que las imágenes aprobadas en `Shot->storyboard_image_path` sean reutilizadas automáticamente por el renderizador de episodios sin regenerarlas.
3. **Tests y Documentación:**
   - Tests PHPUnit para `BroadcastFramePromptBuilder` (ausencia de etiquetas `"ESCENA 1:"` / `"Action & Composition:"` en modo `full_bleed_frame`), persistencia de configuración y flujo Livewire de prueba por plano.
   - Actualizar `jg_filmmaker/PROJECT_CONTEXT.md`.
