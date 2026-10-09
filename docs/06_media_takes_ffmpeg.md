# 06. Gestión de Medios y Ensamble FFmpeg (`jg_filmmaker`)

[← 05. Integración HMAC](05_hmac_agent_integration.md) | [07. Estrategia de Testing y CI/CD →](07_testing_quality_cicd.md)

---

## 1. Arquitectura de Almacenamiento y Carga Multimedia

La fase de rodaje genera clips de vídeo de cada toma registrada en set. `jg_filmmaker` implementa una arquitectura segura y aislada para la subida y almacenamiento físico de estos archivos:

### 1.1. Ingesta Reactiva con Livewire
- Se emplea el trait `Livewire\WithFileUploads`.
- Validación estricta en servidor:
  - Formatos admitidos: `mp4`, `mov`, `webm`, `avi`, `mkv`.
  - Tamaño máximo por archivo: 500 MB (`max:512000`).

### 1.2. Particionado Físico por UUID
Para evitar colisiones de nombres de archivo y permitir escalabilidad en disco:
```text
storage/app/public/media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.mp4
```
La ruta relativa se almacena en la columna `takes.file_path` y se expone públicamente vía URL generada con `Storage::disk('public')->url($take->file_path)`.

---

## 2. Metadatos Técnicos y Selección Editorial

Cada registro en la tabla `takes` incluye:
- `is_good_take` (booleano): Indica si la toma ha sido aprobada por el director/script para pasar a la línea de montaje.
- `editorial_notes` (texto): Observaciones de rodaje (foco, interpretación, sonido, continuidad).
- `technical_meta` (JSONB):
  ```json
  {
    "codec": "h264",
    "resolution": "1920x1080",
    "fps": 24.0,
    "duration_sec": 14.5,
    "size_bytes": 48291040
  }
  ```

---

## 3. Servicio de Ensamble y Pre-corte (`RoughCutAssemblyService`)

El servicio [RoughCutAssemblyService](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/RoughCutAssemblyService.php) automatiza la creación de un máster preliminar de vídeo uniendo todas las tomas aprobadas:

### 3.1. Concatenación con Demuxer de FFmpeg
1. Consulta las tomas marcadas como `is_good_take = true` para la escena o proyecto, ordenadas por escena y número de plano.
2. Genera un archivo de manifiesto temporal para el demuxer concat de FFmpeg:
   ```text
   file '/ruta/absoluta/a/take_1.mp4'
   file '/ruta/absoluta/a/take_2.mp4'
   file '/ruta/absoluta/a/take_3.mp4'
   ```
3. Ejecuta el comando FFmpeg optimizado:
   ```bash
   ffmpeg -y -f concat -safe 0 -i manifest.txt -c copy /ruta/renders/rough_cut_TIMESTAMP.mp4
   ```
4. Si la copia directa sin recompresión (`-c copy`) falla debido a diferencias en los streams de las cámaras, el servicio conmuta automáticamente a transcodificación unificada (H.264 / AAC a 24fps).
5. En entornos de testing o CI/CD donde no hay clips reales de vídeo ni binarios multimedia externos, el servicio genera un fichero representativo determinista en disco garantizando que los tests se ejecuten con éxito inmediato.
