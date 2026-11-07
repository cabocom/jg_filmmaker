# Feature: Generación de Storyboard por Plano con Google AI Studio, Consistencia de Personaje y Estilos Visuales

**Fecha:** 2026-11-07  
**Proyecto:** `jg_filmmaker` (Plataforma Web de Pipeline Cinematográfico Asistido por IA)  
**Autor:** Antigravity (Ingeniero Senior de IA y Arquitectura Visual)  
**Estado:** Propuesta / En Planificación  

---

## 1. Contexto y Objetivos

En el flujo de trabajo de preproducción cinematográfica de `jg_filmmaker`, tras redactar el guión literario y realizar el desglose de planos técnicos (`Shots`), el siguiente paso crucial para el director es la visualización plástica de la escena mediante un **Storyboard**.

El objetivo de esta funcionalidad es permitir la generación automática de la viñeta visual de cada plano utilizando **Google AI Studio** (Imagen 3 / Gemini API), garantizando dos requisitos fundamentales de calidad cinematográfica:

1. **Consistencia Visual de Personajes (Character Conditioning):**  
   Si el usuario ha subido una imagen de referencia o *turnaround sheet* en la ficha del personaje (Biblia de Producción), el generador utilizará dicha fisonomía para que el actor mantenga su rostro, peinado y rasgos a lo largo de todos los planos del guión.

2. **Selector de Estilos de Storyboard:**  
   El cineasta podrá elegir el tono visual de la obra entre tres estéticas contrastadas y bien definidas:
   - **Escala de Grises:** Boceto cinematográfico clásico en carboncillo, grafito y aguadas en blanco y negro, priorizando composición de luces, sombras y encuadre.
   - **Animación 2D Infantil:** Estilo dibujo animado tradicional, líneas limpias y colores vivos, ideal para producciones familiares o animación.
   - **Enki Bilal:** Estética de novela gráfica de autor europea (estilo *Nikopol / La feria de los inmortales*), caracterizada por tonos desaturados, cielos azulados u ocres, texturas pictóricas densas, trazo sombrío y melancolía retrofuturista/distópica.

3. **Puente hacia el Pipeline de Vídeo (Image-to-Video / Flow):**  
   Las imágenes de storyboard generadas actuarán como *Frame 0* (Start Frame) para alimentar herramientas de generación de vídeo (I2V), asegurando máxima coherencia en las tomas finales.

---

## 2. Arquitectura de la Solución

```text
+-----------------------------------------------------------------------------+
|              BIBLIA DE PRODUCCIÓN (ContextBibleDrawer / Character)          |
|  - Subida de imagen de referencia del personaje (Turnaround / Concept Art)   |
|  - Almacenamiento en storage/app/public/characters/{uuid}.jpg               |
+-----------------------------------------------------------------------------+
                                       │
                                       ▼ (Asociación automática al Plano)
+-----------------------------------------------------------------------------+
|                 ScriptEditor: Pestaña "4. Planos" (Shot Card)               |
|  - Selector de Estilo: [ Escala de Grises | Animación 2D | Enki Bilal ]     |
|  - Botón: "🎨 Generar Storyboard" en cada Plano individual                  |
+-----------------------------------------------------------------------------+
                                       │
                                       ▼ (Wire:click "generateStoryboard(shotId)")
+-----------------------------------------------------------------------------+
|                      StoryboardGenerationService                            |
|  1. Compone prompt cinemático (óptica, ángulo, acción, luz, decorado)       |
|  2. Inyecta descriptor estilístico seleccionado                             |
|  3. Adjunta imagen de referencia del personaje como conditioning visual     |
+-----------------------------------------------------------------------------+
                                       │
                                       ▼ (POST /v1beta/models/imagen-3.0...:predict)
+-----------------------------------------------------------------------------+
|                      Google AI Studio (Gemini / Imagen 3)                   |
|  - Generación fotorrealista / estilizada con aspect ratio 16:9 widescreen    |
+-----------------------------------------------------------------------------+
                                       │
                                       ▼ (Descarga y persistencia binaria)
+-----------------------------------------------------------------------------+
|  storage/app/public/storyboards/{project_id}/{shot_id}.jpg                  |
|  - Shot.storyboard_image_path actualizado                                   |
|  - Renderizado reactivo en la tarjeta del plano y visor de Storyboard       |
+-----------------------------------------------------------------------------+
```

---

## 3. Especificación de los 3 Estilos de Storyboard

| Identificador | Nombre Visible | Modificadores del Prompt Maestro en Google AI Studio |
|---|---|---|
| `grayscale_sketch` | **Escala de Grises** | `Cinematic production storyboard sketch, charcoal and graphite drawing, black and white tones, dynamic lighting, film noir shading, rough crosshatching, rule of thirds composition, 35mm storyboard aspect ratio 16:9.` |
| `kids_2d_animation` | **Animación 2D Infantil** | `Vibrant 2D children's animated feature film still, clean vector linework, whimsical warm color palette, soft cel shading, expressive character design, storybook aesthetic, high quality 2D animation, 16:9.` |
| `enki_bilal` | **Enki Bilal** | `Graphic novel panel in the distinct art style of Enki Bilal, European bande dessinée, textured chalk and oil pastel, gritty dystopian atmosphere, desaturated muted color palette with chalky blues and ochre, weathered look, expressive melancholy gaze, cinematic sci-fi noir, 16:9.` |

---

## 4. Desglose en 4 Fases de Implementación

### Fase 1: Extensión de Modelos y Soporte de Imagen de Referencia de Personaje
- **Migración de Base de Datos:**
  - Agregar columna nullable `reference_image_path` a la tabla `characters`.
  - Agregar columnas `storyboard_image_path`, `storyboard_style` y `storyboard_prompt` a la tabla `shots`.
  - Agregar columna `storyboard_style` a la tabla `projects` (con valor por defecto `'grayscale_sketch'`).
- **Biblia de Producción (`ContextBibleDrawer`):**
  - Incorporar selector de subida de imagen (PNG/JPG hasta 5 MB) en el modal/formulario de edición de personaje con previsualización en miniatura.
  - Eliminación segura del fichero anterior al reemplazar o borrar el personaje.

### Fase 2: Servicio de Integración con Google AI Studio (`StoryboardService`)
- **Configuración (`config/services.php`):**
  - Variables de entorno: `GOOGLE_AI_API_KEY` (o `GEMINI_API_KEY`), `GOOGLE_AI_IMAGEN_MODEL` (por defecto `imagen-3.0-generate-002`) y `GOOGLE_AI_BASE_URL`.
- **Implementación del Servicio:**
  - Método `buildPromptForShot(Shot $shot, string $style): array`: combina el tipo de plano (`shot_type`), la descripción de la acción, el slugline/decorado de la escena y las notas de arte de la Biblia.
  - Método `generateShotStoryboard(Shot $shot, ?string $style = null): string`: realiza la llamada autenticada a la API de Google AI Studio, enviando el prompt y la imagen del personaje (como referencia multimodal si el plano tiene personaje protagonista asignado).
  - Almacenamiento seguro del resultado en disco público (`storage/app/public/storyboards/...`) con retorno de URL pública.
  - Mecanismo de fallback y aislamiento determinista para el entorno de pruebas (`testing`).

### Fase 3: Experiencia de Usuario en el Estudio (`ScriptEditor`)
- **Controles en la Pestaña de Planos Técnicos:**
  - Selector de estilo de storyboard en la cabecera de la sección de planos (Escala de Grises, Animación 2D Infantil, Enki Bilal).
  - En cada tarjeta de plano (`Shot`), botón `🎨 Generar Storyboard` (y `🔄 Regenerar`).
  - Indicador de estado de carga en el plano mientras se genera la imagen.
  - Visualización de la miniatura 16:9 del storyboard generado con opción de ver a pantalla completa (lightbox).
  - Si el plano tiene una imagen de personaje asociada, muestra una pequeña insignia indicando *"Condicionado con imagen de [Nombre Personaje]"*.

### Fase 4: Pruebas Automatizadas, Validación CI/CD y Documentación
- Suite completa de pruebas automatizadas:
  - Pruebas unitarias de construcción de prompts según los 3 estilos seleccionados.
  - Pruebas de integración simulando peticiones exitosas y fallidas a Google AI Studio (`Http::fake()`).
  - Pruebas de persistencia y autorización (mitigación IDOR: solo el propietario del proyecto puede generar y consultar imágenes).
- Validación de linters con `./vendor/bin/pint --test` y ejecución de la suite completa con `php artisan test`.
- Actualización de `PROJECT_CONTEXT.md` y publicación del issue en GitLab.com.
