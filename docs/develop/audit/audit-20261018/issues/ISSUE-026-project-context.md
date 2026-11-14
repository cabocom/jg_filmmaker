# Actualización de Contexto del Proyecto: ISSUE-026

## Componente Afectado
`jg_filmmaker` (`config/livewire.php`, `docker/php.ini`, `docker/nginx.conf`).

## Comportamiento Actualizado
- Límites de subida consistentes en todas las capas del stack a 100-120 MB.
- Subida de tomas cinematográficas reales de hasta 100 MB operativa sin interrupciones por filtros temporales previos.
- Ventana de subida ampliada a 30 minutos y rate limiting a 15 uploads por minuto.
