# [ISSUE-028] Middleware `EnsureDemoUserAuthenticated` autologuea como admin en entorno `testing` y está registrado globalmente; enmascara la autenticación en las pruebas

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Http/Middleware/EnsureDemoUserAuthenticated.php:20-41`, `bootstrap/app.php:17-19`
- **Categoría:** Seguridad

## 1. Descripción del Problema
El middleware se añade al grupo `web` y, si `APP_ENV=testing` y no hay sesión, crea/inicia sesión como `filmmaker@cabocom.net` con `is_admin=true` y contraseña conocida. Riesgos:
- Cualquier despliegue (o job de CI/preview) con `APP_ENV=testing` queda con **acceso admin anónimo**. Una variable mal escrita en el compose basta (ISSUE-007 deja `APP_ENV` configurable).
- En los tests, **todas** las peticiones sin `actingAs` se ejecutan como administrador, de modo que un test de "usuario sin sesión" jamás falla y los fallos de autorización pasan desapercibidos (los tests `assertStatus(403)` sólo existen en 2 ficheros).
- Contraseña estática embebida en el código (`secret1234`), almacenada también en BD al crearse.
- `catch (Throwable)` silencia errores de BD.

## 2. Evidencia de Código
```php
if (app()->environment('testing') && ! Auth::check() && ! $request->is('login*', 'logout*')) {
    $user = User::firstOrCreate(['email' => 'filmmaker@cabocom.net'], [... 'password' => Hash::make('secret1234'), 'is_admin' => true]);
    Auth::login($user);
}
```
```php
$middleware->web(append: [EnsureDemoUserAuthenticated::class]);
```

## 3. Impacto Operativo o de Seguridad
Escalada completa si el entorno se etiqueta como `testing`; falsa sensación de cobertura de seguridad en la suite.

## 4. Solución Recomendada
- Eliminar el middleware y usar `actingAs()` explícito en los tests; el helper `$this->be(User::factory()->admin()->create())` sólo en los tests que lo requieran.
- Si se conserva, registrarlo únicamente en `local` y detrás de `config('app.demo_autologin')` desactivado por defecto, con un guard `abort_if(app()->isProduction())`.
- Añadir tests de invitado (302 a `/login`) para cada ruta.
