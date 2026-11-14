# ISSUE-028 Walkthrough: Desactivación de Autologin Global y Aislamiento de Entorno

## Resumen de Cambios

1. **Eliminación del Middleware Global:**
   - En `bootstrap/app.php`, se eliminó `EnsureDemoUserAuthenticated` del array `append` del grupo `web`.
   - Se mantiene únicamente como alias opcional `demo.auth` para desarrollo local controlado.

2. **Confinamiento a Entorno Local:**
   - En `EnsureDemoUserAuthenticated::handle()`, se exige `app()->environment('local')` y `config('app.demo_autologin', false) === true`.
   - Queda estrictamente anulado en `production` y `testing`.
   - Se reemplazó la contraseña hardcodeada `secret1234` por un hash aleatorio generado criptográficamente.

3. **Verificación Automatizada:**
   - `test_unauthenticated_request_to_admin_or_protected_routes_redirects_to_login` en `tests/Feature/AdminSecurityAndDemoIsolationTest.php` valida que las peticiones de invitados a rutas protegidas redirigen a `/login` sin iniciar sesión automáticamente.
   - `test_ensure_demo_user_authenticated_does_not_autologin_in_testing` comprueba que el middleware no realiza auto-login en entorno de pruebas.
