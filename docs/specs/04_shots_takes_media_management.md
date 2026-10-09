# 04. Desglose Técnico, Gestión de Tomas y Media

## 1. Detección y Marcaje de Planos
Se mantiene la compatibilidad con el sistema de marcaje de Film Manager:
- Cada escena contiene un listado ordenado de planos generados o editados por el usuario.
- Patrón canónico de detección:
  `--- shot N (tipo) ---`
  Ejemplo:
  `--- shot 1 (PG) ---`
  `Juan y Javi sentados a la mesa del café.`
  `--- shot 2 (PM) ---`
  `Juan mira fijamente a Javi esperando una respuesta.`

## 2. Gestión de Tomas y Metadatos de Video
Cada plano (`Shot`) tiene una relación 1 a N con tomas (`Takes`).

### Estructura de Almacenamiento
- Subida directa o encolada (Laravel Storage con driver seguro local o S3).
- Ruta física particionada por UUID para evitar colisiones:
  `media/projects/{project_uuid}/scenes/{scene_uuid}/takes/{take_uuid}.mp4`

### Metadatos y Posproducción
Cada registro de `Take` registra:
- `is_good_take` (boolean): Marca para tomas buenas / aprobadas para edición.
- `editorial_notes` (text): Anotaciones específicas (ej: "Cortar antes del carraspeo", "Mejorar etalonaje por sombra en fondo").
- `technical_meta` (JSONB):
  - `duration_seconds`: float
  - `resolution`: string (ej: "3840x2160")
  - `codec`: string (ej: "h264", "prores")
  - `fps`: float (ej: 24.0, 25.0)

## 3. Integración con Montaje (Timeline Preliminar)
- La aplicación permitirá filtrar exclusivamente las tomas marcadas con `is_good_take = true`.
- Generación de una lista de montaje ordenada (EDL preliminar o exportable JSON) para su visualización secuencial en el reproductor web.
