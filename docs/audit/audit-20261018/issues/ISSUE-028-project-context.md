# Actualización de Contexto del Proyecto: ISSUE-028

## Componente Afectado
`jg_filmmaker` (`EnsureDemoUserAuthenticated`, `bootstrap/app.php`).

## Comportamiento Actualizado
- Middleware demo desacoplado de la pila web global; no se ejecuta en testing ni en producción.
- Autenticación en pruebas 100% explícita mediante `actingAs()`, sin enmascaramiento de sesiones anónimas.
- Eliminación de credenciales estáticas de desarrollo.
