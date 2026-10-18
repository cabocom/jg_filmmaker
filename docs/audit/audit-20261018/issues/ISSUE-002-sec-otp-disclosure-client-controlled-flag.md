# [ISSUE-002] Divulgación del OTP de login mediante propiedad Livewire manipulable (`isLocalEnv`) y cabecera `Host`: toma de cuenta (incl. admin)

- **Severidad:** Crítica
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Auth/Login.php:30,38-45,106-109`
- **Categoría:** Seguridad

## 1. Descripción del Problema
El componente `Login` decide si "es entorno local" y, si lo es, **devuelve el código OTP en claro al navegador** (`devOtpCode`). Esa decisión se apoya en:

1. `public bool $isLocalEnv` — **propiedad pública Livewire sin `#[Locked]`**: el cliente puede enviarla modificada en el payload `updates` de `/livewire/update`. `requestOtp()` lee `$this->isLocalEnv` (no la recalcula) en la línea 106.
2. `request()->getHost() === 'localhost'` / `str_contains(getHost(), '127.0.0.1')` — derivado de la cabecera `Host`, controlable por el atacante (el nginx usa `server_name _`).
3. `request()->ip()` en `['127.0.0.1','::1']` — detrás de proxy inverso/healthchecks puede ser siempre loopback.

Un atacante envía `requestOtp` con `isLocalEnv=true` para cualquier email (por ejemplo el del admin sembrado `filmmaker@cabocom.net`) y recibe el PIN en la respuesta.

## 2. Evidencia de Código
```php
public bool $isLocalEnv = false;                      // <- editable desde el cliente
...
$this->isLocalEnv = app()->environment('local')
    || in_array(request()->ip(), ['127.0.0.1', '::1'], true)
    || request()->getHost() === 'localhost'
    || str_contains(request()->getHost(), '127.0.0.1');
...
if ($this->isLocalEnv || app()->environment('local')) {   // <- usa la propiedad del cliente
    $this->devOtpCode = $code;
    session(['dev_otp_code' => $code]);
}
```

## 3. Impacto Operativo o de Seguridad
Bypass completo de la autenticación passwordless: suplantación de cualquier usuario, incluidos administradores (`/admin/*`: transferencia de proyectos, toggle de admin, borrado de logs de seguridad). Acceso a todos los guiones.

## 4. Solución Recomendada
- Eliminar `isLocalEnv` como estado de cliente; calcularlo siempre en servidor y **solo** con `app()->environment('local')` (nunca con Host/IP):
```php
#[Locked] public ?string $devOtpCode = null;   // y no exponer el flag

private function exposeDevOtp(): bool
{
    return app()->environment('local') && config('app.debug') === true;
}
```
- Marcar con `#[Locked]` toda propiedad sensible (`step`, `devOtpCode`).
- No guardar el código en sesión (`dev_otp_code`).
- Test: invocar `requestOtp` con `->set('isLocalEnv', true)` bajo `APP_ENV=production` y comprobar que `devOtpCode` es `null`.
- Revisar ISSUE-007 (el compose arranca por defecto con `APP_ENV=local`).
