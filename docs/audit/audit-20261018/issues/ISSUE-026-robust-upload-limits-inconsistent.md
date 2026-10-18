# [ISSUE-026] Subida temporal de Livewire limitada a 12 MB y sin throttle propio, incoherente con la validación de 100 MB y el límite de nginx de 512 MB

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `config/livewire.php:133-135`, `app/Livewire/Studio/ScriptEditor.php:73,1488`, `docker/php.ini:3-4`, `docker/nginx.conf:29`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
- La subida de archivos Livewire pasa primero por el endpoint temporal con `rules => null` → regla por defecto `['required','file','max:12288']` (12 MB). El `uploadTakeVideo` valida `max:102400` (100 MB), así que **cualquier toma real de más de 12 MB falla antes** de llegar a esa validación (error genérico de Livewire). El test usa `UploadedFile::fake()` (5000 KB) y no detecta el límite.
- Tres límites inconsistentes: Livewire 12 MB, validación 100 MB, nginx/PHP 512 MB. Con 512 MB de `client_max_body_size` y `post_max_size` cualquier endpoint (incluido `/livewire/update`) acepta cuerpos de hasta 512 MB: amplificación de DoS (con `memory_limit 512M`) para usuarios autenticados.
- `throttle` por defecto 60/min en el upload temporal; no se limita volumen total por usuario/proyecto (cuota de almacenamiento inexistente): un usuario puede llenar el disco.
- Los ficheros temporales (`livewire-tmp`) en disco por defecto no se purgan si el cliente aborta tras subir (la purga depende de `livewire:configure-s3-upload-cleanup` sólo para S3).
- El valor `max_upload_time` de 5 minutos puede invalidar subidas de vídeos grandes en conexiones lentas.

## 2. Evidencia de Código
```php
'rules' => null,       // Default: ['required', 'file', 'max:12288'] (12MB)
```
```php
'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|max:102400',
```

## 3. Impacto Operativo o de Seguridad
La función de subida de tomas no funciona con vídeo real (> 12 MB), y los límites generosos del servidor abren la puerta a abuso de recursos.

## 4. Solución Recomendada
- Configurar `temporary_file_upload.rules` = `['required','file','mimetypes:video/mp4,video/quicktime,video/webm','max:102400']` y `middleware => 'throttle:10,1'`.
- Unificar límites (por ejemplo 100 MB en las tres capas) y aplicar `client_max_body_size 100M` sólo en el `location` del upload, 2 MB en el resto.
- Cuota de almacenamiento por usuario/proyecto.
- Job de limpieza de `livewire-tmp` (> 24 h).
- Test de integración con un fichero de ~20 MB.
