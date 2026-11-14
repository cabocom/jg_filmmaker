# ISSUE-026 Walkthrough: Alineación de Límites de Subida de Archivos

## Resumen de Cambios

1. **Configuración de Subida Temporal de Livewire:**
   - En `config/livewire.php`, se actualizaron las reglas temporales a:
     `'rules' => ['required', 'file', 'mimes:mp4,mov,webm,m4v', 'max:102400']` (100 MB).
   - Se configuró `middleware => 'throttle:15,1'` para mitigar abusos de subidas masivas.
   - Se aumentó `max_upload_time` a 30 minutos para garantizar la fiabilidad en conexiones con ancho de banda reducido.

2. **Alineación de Infraestructura Docker:**
   - En `docker/php.ini`, se ajustaron `upload_max_filesize` y `post_max_size` a 120M.
   - En `docker/nginx.conf`, se ajustó `client_max_body_size` a 120M.

3. **Verificación Automatizada:**
   - `test_livewire_temporary_upload_is_configured_for_100mb_video_takes` en `tests/Feature/AdminPaginationAndUploadConfigTest.php` valida que las directivas de configuración de Livewire permiten 100 MB con throttle adecuado.
