# Análisis Técnico: [ISSUE-001] Subida de tomas permite extensión arbitraria controlada por el cliente (RCE vía `.php` en `/storage`)

- **Componente:** `jg_filmmaker`
- **Severidad:** Crítica
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidad:** CWE-434 (Unrestricted Upload of File with Dangerous Type) / RCE (Remote Code Execution)

---

## 1. Diagnóstico del Estado Previo

En la implementación actual del estudio de rodaje y subida de tomas (`ScriptEditor::uploadTakeVideo`):

1. **Confianza ciega en `getClientOriginalExtension()`:**
   En `app/Livewire/Studio/ScriptEditor.php:1498`:
   ```php
   $extension = $this->videoUpload->getClientOriginalExtension() ?: 'mp4';
   $storagePath = $take->generateStoragePath($extension);
   $this->videoUpload->storeAs('', $storagePath, 'public');
   ```
   Aunque la validación de Livewire incluye `'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|max:102400'`, la regla `mimes:` de Laravel inspecciona el MIME type deducido por `fileinfo` o el contenido de los primeros bytes (`magic bytes`). Un archivo políglota (e.g. cabecera binaria `ftypisom` de MP4 seguido de un payload PHP `<?php phpinfo(); ?>`) pasa la validación de MIME como `video/mp4`, pero conserva la extensión original del cliente (`.php`).

2. **Falta de lista blanca en `Take::generateStoragePath()`:**
   En `app/Models/Take.php:80-88`:
   ```php
   public function generateStoragePath(string $extension = 'mp4'): string
   {
       $cleanExt = ltrim(strtolower($extension), '.');
       return "media/projects/{$projectId}/scenes/{$sceneId}/takes/{$this->id}.{$cleanExt}";
   }
   ```
   El método no restringe la extensión a una lista blanca cerrada de formatos de vídeo (`mp4`, `mov`, `webm`, `m4v`), permitiendo extensiones peligrosas como `.php`, `.phtml` o `.phar`.

3. **Exposición y ejecución en servidor Nginx (`docker/nginx.conf`):**
   El archivo `docker/nginx.conf` enrutaba todas las peticiones terminadas en `\.php$` hacia PHP-FPM (`fastcgi_pass 127.0.0.1:9000`) sin verificar la existencia del archivo (`try_files $uri =404;`) y sin excluir la ruta `/storage/`. Dado que el disco `public` se expone en `public/storage`, un archivo subido con extensión `.php` se ejecuta directamente en el proceso FastCGI con los privilegios del usuario `www-data`.

---

## 2. Alternativas Evaluadas

1. **Confiar únicamente en la regla `extensions:mp4,mov,webm,m4v` de validación de Laravel:**
   - *Descartada como solución única:* Aunque añade una capa en la validación, no protege si se invoca `generateStoragePath` desde otros contextos ni garantiza que la extensión guardada se derive canónicamente del MIME type verificado por el servidor.
2. **Derivación estricta del MIME type detectado + Lista blanca en modelo + Blindaje en Nginx (Solución Seleccionada):**
   - *Ventajas (Defensa en Profundidad):*
     - En `ScriptEditor`: se obtiene `$this->videoUpload->getMimeType()` y se mapea mediante un diccionario estricto a extensiones seguras (`video/mp4` -> `mp4`, `video/quicktime` -> `mov`, `video/webm` -> `webm`, `video/x-m4v` -> `m4v`). Cualquier MIME no mapeado se rechaza con error de validación.
     - En `Take::generateStoragePath`: se añade una lista blanca inmutable (`ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v']`). Si se pasa cualquier otra extensión, lanza `InvalidArgumentException`.
     - En `docker/nginx.conf`:
       - Se añade directiva `try_files $uri =404;` dentro del bloque `location ~ \.php$`.
       - Se define un bloque prioritario `location ^~ /storage/` que bloquea tajantemente la ejecución de scripts (`location ~ \.php$ { deny all; }`) y añade cabeceras defensivas `X-Content-Type-Options: nosniff`.

---

## 3. Plan de Implementación

1. **Modelo `Take` (`app/Models/Take.php`):**
   - Definir constante pública `ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v']`.
   - Validar en `generateStoragePath(string $extension)` que la extensión limpia figure en `ALLOWED_EXTENSIONS`; de lo contrario, lanzar `\InvalidArgumentException`.

2. **Componente `ScriptEditor` (`app/Livewire/Studio/ScriptEditor.php`):**
   - Actualizar regla de validación agregando `extensions:mp4,mov,webm,m4v`.
   - Derivar `$extension` mapeando `$this->videoUpload->getMimeType()` contra un mapa canónico seguro.
   - En caso de discrepancia, emitir error de validación y abortar la subida sin escribir en disco.

3. **Configuración de Nginx (`docker/nginx.conf`):**
   - Proteger `location ^~ /storage/` denegando explícitamente cualquier `.php` y agregando cabecera `nosniff`.
   - Añadir `try_files $uri =404;` en `location ~ \.php$`.

4. **Pruebas de Regresión (`tests/Feature/TakeMediaUploadTest.php`):**
   - Añadir test que intente subir un archivo con nombre malicioso `exploit.php` y verificar que se almacena con la extensión válida del formato o es rechazado.
   - Añadir test unitario/funcional que compruebe que `Take::generateStoragePath('php')` lanza `InvalidArgumentException`.
