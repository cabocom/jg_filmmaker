# [ISSUE-019] Evolución de Almacenamiento Local/FUSE hacia S3 / MinIO / Cloudflare R2 con Presigned URLs

- **Severidad:** Idea de Mejora
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/config/filesystems.php:31-63`, `jg_filmmaker/app/Http/Controllers/MediaStreamController.php:18-63`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:2425-2485`
- **Categoría:** Arquitectura

## 1. Descripción
En la topología actual de producción (documentada en `PROJECT_CONTEXT.md` bajo `ISSUE-067`), el directorio `/var/www/html/storage/app` está montado sobre Google Drive mediante FUSE `rclone` (`/mnt/gdrive_filmmaker`), mientras que `storage/tts_cache` reside en el disco NVMe local. Además, el código en `MediaStreamController`, `StoryboardService`, `RoughCutAssemblyService` y `ContextBibleDrawer` acopla rutas físicas locales (`Storage::disk('local')->path(...)`, `file_exists()`, `response()->file()`) con el sistema de archivos POSIX del contenedor.

Esta arquitectura presenta tres limitaciones estructurales para escalar:
1. **Acoplamiento a `Storage::path()` y `file_exists()` Local:** El método `response()->file(Storage::disk('local')->path($path))` solo funciona cuando el disco es `local` (POSIX). Si se cambia `FILESYSTEM_DISK=s3` (AWS S3, Cloudflare R2 o MinIO), `Storage::disk('s3')->path(...)` no apunta a un archivo local existente y rompe el streaming de tomas, renders y la lectura de imágenes en `StoryboardService`.
2. **Penalización de Latencia I/O sobre FUSE rclone:** Subir una toma de vídeo de 100 MB a través de Livewire escribe primero en el directorio temporal de Livewire y luego la mueve al punto de montaje FUSE de Google Drive, bloqueando operaciones de lectura/escritura y haciendo que `ffprobe` o `ffmpeg` operen sobre un sistema de archivos en red no diseñado para acceso aleatorio de bloques de vídeo.
3. **Imposibilidad de Despliegue Multi-Nodo (Escalado Horizontal):** Si se levantan 2 réplicas del contenedor `jg_filmmaker` tras un balanceador de carga, las subidas temporales de Livewire (`livewire-tmp`) y la caché local `storage/tts_cache` quedan aisladas por contenedor.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Http/Controllers/MediaStreamController.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Http/Controllers/MediaStreamController.php#L30-L39):
```php
        if (! $take->file_path || ! Storage::disk('local')->exists($take->file_path)) {
            abort(404, 'Archivo de vídeo de toma no encontrado.');
        }

        $absolutePath = Storage::disk('local')->path($take->file_path);

        return response()->file($absolutePath, [
            'Content-Type' => mime_content_type($absolutePath) ?: 'video/mp4',
            'Cache-Control' => 'private, max-age=3600',
        ]);
```

## 3. Impacto Operativo o Riesgo
- **Cuello de Botella de Ancho de Banda en PHP-FPM:** Servir archivos de vídeo de decenas de megabytes desde PHP (`response()->file()`) mantiene ocupado al worker PHP-FPM durante la transferencia de cada chunk HTTP Range en lugar de delegar la descarga al servidor de objetos o CDN.

## 4. Solución o Mejora Recomendada
Diseñar una capa de abstracción de almacenamiento agnóstica del driver (`MediaStorageService`) compatible tanto con disco local en desarrollo como con **S3 / Cloudflare R2 / MinIO** en producción:

1. **Entrega mediante `temporaryUrl()` (Presigned GET URLs) o `X-Accel-Redirect`:**
   - Si el disco configurado soporta URLs temporales firmadas (`s3` / `r2` / `minio`), `MediaStreamController` valida la autorización (`ProjectPolicy::view`) y devuelve un `redirect()->away(Storage::disk('media')->temporaryUrl($path, now()->addMinutes(15)))`. El navegador realiza los *HTTP Range Requests* directamente contra el Object Storage sin tocar PHP-FPM.
   - Si el disco es `local` tras Nginx, utilizar la cabecera interna `X-Accel-Redirect` para que Nginx sirva el archivo directamente desde el kernel (`sendfile on`).
2. **Subida Directa a Object Storage:** Configurar `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3` para que las tomas de hasta 100 MB se suban directamente desde el navegador del cineasta al bucket mediante URLs pre-firmadas S3 sin pasar por la memoria del contenedor PHP.

