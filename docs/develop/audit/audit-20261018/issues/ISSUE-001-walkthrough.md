# Walkthrough Técnico: [ISSUE-001] Subida de tomas permite extensión arbitraria controlada por el cliente (RCE vía `.php` en `/storage`)

- **Componente:** `jg_filmmaker`
- **Severidad:** Crítica
- **Fecha:** 18 de octubre de 2026

---

## 1. Archivos Modificados

### 1.1. Modelo `Take`
- [`jg_filmmaker/app/Models/Take.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Take.php):
  - Se define la constante pública `public const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v'];`.
  - En `generateStoragePath(string $extension)` se valida explícitamente que la extensión (limpia y en minúsculas) pertenezca a `ALLOWED_EXTENSIONS`. Si no está permitida, se lanza `\InvalidArgumentException`.

### 1.2. Componente Livewire de Estudio
- [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php):
  - En `uploadTakeVideo()`:
    - Se añade la regla de validación `extensions:mp4,mov,webm,m4v` junto a `mimes:mp4,mov,webm,m4v,quicktime`.
    - Se elimina el uso directo de `$this->videoUpload->getClientOriginalExtension()`.
    - Se mapea el MIME type real del archivo subido (`$this->videoUpload->getMimeType()`) contra una tabla cerrada de tipos permitidos (`video/mp4` -> `'mp4'`, `video/quicktime` -> `'mov'`, `video/webm` -> `'webm'`, `video/x-m4v` -> `'m4v'`).
    - Si el MIME no es válido, se emite un error de validación `addError('videoUpload', ...)` y se interrumpe la ejecución sin tocar disco.

### 1.3. Configuración de Nginx
- [`jg_filmmaker/docker/nginx.conf`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docker/nginx.conf):
  - Se añade directiva `try_files $uri =404;` dentro del bloque `location ~ \.php$`.
  - Se añade directiva prioritaria `location ^~ /storage/` que bloquea tajantemente la ejecución de archivos PHP (`location ~ \.php$ { deny all; }`) y añade la cabecera `X-Content-Type-Options nosniff`.

### 1.4. Pruebas Automatizadas
- [`jg_filmmaker/tests/Feature/TakeMediaUploadTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/TakeMediaUploadTest.php):
  - `test_generate_storage_path_rejects_unauthorized_extensions`: comprueba que invocar `generateStoragePath('php')` dispara `InvalidArgumentException`.
  - `test_upload_take_video_rejects_dangerous_extensions_and_unauthorized_mimes`: valida que intentar subir un archivo con extensión `.php` es rechazado con errores de validación sin almacenar nada en disco.

---

## 2. Pruebas de Verificación y No Regresión

- **Linter de código:** `./vendor/bin/pint --test` (Passed)
- **Suite de pruebas:** `php artisan test` (84 passed, 518 assertions)
- **Pipeline CI/CD:** `./scripts/check.sh --cicd -p jg_filmmaker` (Passed)
