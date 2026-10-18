# ISSUE-028 Análisis: Aislamiento del Middleware de Autologin Demo

## 1. Diagnóstico del Problema
- El middleware `EnsureDemoUserAuthenticated` estaba registrado de forma global en `$middleware->web(append: [...])` en `bootstrap/app.php`.
- En entorno `testing`, si no había sesión activa, iniciaba automáticamente sesión como `filmmaker@cabocom.net` con rol `is_admin=true` y contraseña estática embebida en el código (`secret1234`).
- Consecuencias de seguridad:
  1. Si un despliegue de preview o staging se marcaba con `APP_ENV=testing`, cualquier usuario anónimo obtenía acceso total como administrador del sistema.
  2. En los tests de integración, cualquier petición no autenticada se ejecutaba como administrador, enmascarando fallos de autenticación y autorización en la suite de pruebas.

## 2. Estrategia de Mitigación
1. Eliminar `EnsureDemoUserAuthenticated` de la pila global `$middleware->web(append: [...])` en `bootstrap/app.php`.
2. Confinar la lógica de emulación estrictamente al entorno `local`, detrás de la bandera de configuración explícita `config('app.demo_autologin', false) === true`.
3. Anular cualquier ejecución en `testing` y `production`.
4. Eliminar la contraseña estática `secret1234`, utilizando un generador criptográfico seguro para desarrollo local.
5. Garantizar que en las pruebas automatizadas, las peticiones sin autenticar reciban redirección 302 a `route('login')`.
