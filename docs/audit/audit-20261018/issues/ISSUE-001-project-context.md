# Project Context: [ISSUE-001] Blindaje contra RCE en la Subida de Tomas

- **Componente:** `jg_filmmaker`
- **Fecha de aplicación:** 18 de octubre de 2026
- **Estado del Issue:** Resuelto y Verificado
- **Severidad Original:** Crítica

---

## 1. Resumen de la Modificación
Se eliminó la vulnerabilidad de Ejecución Remota de Código (RCE) en la funcionalidad de subida de vídeos de tomas (`ScriptEditor::uploadTakeVideo`). Previamente, el sistema confiaba en `getClientOriginalExtension()`, permitiendo a usuarios autenticados subir ficheros políglotas con extensión `.php` al disco `public`, expuestos en `/storage/` y ejecutables por FastCGI.

## 2. Cambios Arquitectónicos y de Seguridad
1. **Derivación Estricta de Extensiones en Servidor:**
   - La extensión se infiere ahora exclusivamente del MIME type validado por el servidor (`$this->videoUpload->getMimeType()`) contra una tabla blanca estricta (`video/mp4` -> `'mp4'`, `video/quicktime` -> `'mov'`, `video/webm` -> `'webm'`, `video/x-m4v` -> `'m4v'`).
   - Se refuerza la validación con `extensions:mp4,mov,webm,m4v`.
2. **Lista Blanca en Capa de Modelo:**
   - `Take::ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v']`.
   - `Take::generateStoragePath()` valida la extensión y arroja `InvalidArgumentException` ante extensiones no autorizadas.
3. **Defensa en Profundidad en Nginx:**
   - Se agregó `location ^~ /storage/ { location ~ \.php$ { deny all; } add_header X-Content-Type-Options nosniff; }` en `docker/nginx.conf`.
   - Se incorporó `try_files $uri =404;` en el bloque FastCGI.

## 3. Estado de Calidad y Pruebas
- Pruebas añadidas en `tests/Feature/TakeMediaUploadTest.php`:
  - `test_generate_storage_path_rejects_unauthorized_extensions`
  - `test_upload_take_video_rejects_dangerous_extensions_and_unauthorized_mimes`
- Resultado de validación: 84 tests aprobados (518 aserciones). Pint y CI/CD en estado exitoso (0 fallos de lint).
