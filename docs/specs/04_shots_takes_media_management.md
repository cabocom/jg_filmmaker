# 04. Desglose Técnico, Gestión de Tomas y Media

## 1. Detección y Marcaje de Planos
Se mantiene la compatibilidad con el sistema de marcaje cinematográfico:
- Cada escena contiene un listado ordenado de planos técnicos generados por la IA o editados por el usuario.
- Patrón canónico de detección estándar:
  `--- shot (TIPO DE PLANO) ---` (con numeración incremental automática por escena).
  También se mantiene compatibilidad con la sintaxis numerada legacy:
  `--- shot N (TIPO DE PLANO) ---`
  Ejemplo canónico:
  `--- shot (PG) ---`
  `Juan y Javi sentados a la mesa del café.`
  `--- shot (PM) ---`
  `Juan mira fijamente a Javi esperando una respuesta.`

## 2. Gestión de Tomas y Metadatos de Video
Cada plano (`Shot`) tiene una relación 1 a N con tomas (`Takes`).

### Estructura de Almacenamiento Seguro (Disco Privado)
- Rutas particionadas por UUID para evitar colisiones:
  - Tomas de rodaje: `media/projects/{project_uuid}/takes/{take_uuid}.mp4`
  - Montajes y renders: `media/projects/{project_uuid}/renders/{render_uuid}.mp4`
  - Viñetas de storyboard: `projects/{project_uuid}/storyboards/scene_{scene_id}/shot_{shot_id}_{timestamp}.png`
  - Referencias de la Biblia: `projects/{project_uuid}/characters/{hash}.jpg` y `locations/{hash}.jpg`
  - Atrezzo: `projects/{project_uuid}/props/shot_{shot_id}/...`
- Entrega streaming segura mediante `MediaStreamController` y `AudioStreamController` con verificación estricta de propiedad (`$project->user_id === auth()->id()`), soporte de rangos `bytes` para scrubber de vídeo y cabeceras privadas anti-fuga (`Cache-Control: private, no-cache`).

### Metadatos y Posproducción
Cada registro de `Take` registra:
- `is_good_take` (boolean): Marca para tomas buenas / aprobadas para edición.
- `editorial_notes` (text): Anotaciones específicas de posproducción.
- `technical_meta` (JSONB):
  - `duration_seconds`: float
  - `resolution`: string (ej: "3840x2160")
  - `codec`: string (ej: "h264", "prores")
  - `fps`: float (ej: 24.0, 25.0)

## 3. Integración con Montaje (Timeline y Ensamblaje Asíncrono FFmpeg)
- La aplicación permite filtrar y ordenar las tomas marcadas con `is_good_take = true`.
- **Procesamiento Asíncrono en Colas:** El ensamblaje de vídeo (`AssembleRoughCutJob`) y la exportación de audio a MP3 (`ExportProjectMp3Job`) se ejecutan en segundo plano sobre la cola `media`, protegidos con bloqueos atómicos (`Cache::lock()`) y reporte reactivo de estado (`ready`, `processing`, `error`).
