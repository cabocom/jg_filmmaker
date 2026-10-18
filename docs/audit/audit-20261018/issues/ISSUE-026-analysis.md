# ISSUE-026 Análisis: Alineación de Límites de Subida de Archivos

## 1. Diagnóstico del Problema
- Livewire tenía configurado por defecto el límite de subida temporal en 12 MB (`max:12288`), mientras que el endpoint de subida de tomas (`uploadTakeVideo`) validaba hasta 100 MB (`max:102400`). Como resultado, cualquier subida de vídeo de más de 12 MB fallaba silenciosamente en la capa de transporte de Livewire antes de alcanzar la validación del controlador.
- Además, la configuración de Nginx y PHP permitía cuerpos de hasta 512 MB (`client_max_body_size 512M`, `upload_max_filesize 512M`), creando una disparidad excesiva que abría riesgos de abuso de memoria y DoS en endpoints interactivos.
- El tiempo de expiración temporal de Livewire (`max_upload_time = 5` minutos) era insuficiente para conexiones lentas subiendo tomas en alta definición.

## 2. Estrategia de Mitigación
1. Configurar explícitamente en `config/livewire.php` la regla de subida temporal con `max:102400` (100 MB), mimetypes de vídeo permitidos y `max_upload_time = 30` minutos.
2. Añadir limitador de tasa de subida con `middleware => 'throttle:15,1'`.
3. Ajustar los límites en `docker/php.ini` y `docker/nginx.conf` a 120 MB para armonizar todas las capas del sistema con la política de 100 MB sin riesgo de amplificación de recursos.
