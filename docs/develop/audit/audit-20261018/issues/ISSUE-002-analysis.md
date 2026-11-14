# Análisis Técnico: [ISSUE-002] Divulgación del OTP de login mediante propiedad Livewire manipulable (`isLocalEnv`) y cabecera `Host`: toma de cuenta (incl. admin)

- **Componente:** `jg_filmmaker`
- **Severidad:** Crítica
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidad:** CWE-287 (Improper Authentication) / CWE-200 (Exposure of Sensitive Information) / Authentication Bypass

---

## 1. Diagnóstico del Estado Previo

El componente Livewire `App\Livewire\Auth\Login` implementa el flujo de autenticación passwordless mediante One-Time Passwords (OTP). Durante la auditoría se constató una vulnerabilidad crítica que permite a un atacante remoto obtener el código OTP en texto plano de cualquier cuenta:

1. **Propiedad pública sin bloquear (`public bool $isLocalEnv`):**
   - Livewire expone todas las propiedades públicas como estado cliente-servidor modificable en cada petición AJAX (`/livewire/update`). Al carecer del atributo `#[Locked]`, un atacante puede inyectar `"isLocalEnv": true` en el payload de actualización.
   - En `Login::requestOtp()`, la comprobación `if ($this->isLocalEnv || app()->environment('local'))` evalúa el valor manipulado por el atacante, asignando `$this->devOtpCode = $code` y exponiendo el PIN en la respuesta JSON de Livewire.

2. **Heurísticas de detección de entorno inseguras y vulnerables a spoofing:**
   - En `mount()`, `$isLocalEnv` se calculaba mediante:
     ```php
     $this->isLocalEnv = app()->environment('local')
         || in_array(request()->ip(), ['127.0.0.1', '::1'], true)
         || request()->getHost() === 'localhost'
         || str_contains(request()->getHost(), '127.0.0.1');
     ```
   - La cabecera `Host` es totalmente controlable por el cliente (y Nginx tiene `server_name _`). Un atacante enviando `Host: localhost` o `Host: 127.0.0.1` hacia el servidor de producción activaría la bandera.
   - Detrás de proxies inversos o balanceadores mal configurados, `request()->ip()` puede reflejar la IP de loopback.

3. **Almacenamiento del OTP en sesión de desarrollo:**
   - La persistencia innecesaria en `session(['dev_otp_code' => $code])` amplía la superficie de ataque si la sesión es compartida o retenida en cookies no invalidadas.

---

## 2. Alternativas Evaluadas

1. **Añadir `#[Locked]` a `public bool $isLocalEnv` manteniendo la heurística de IP/Host:**
   - *Descartada:* Insegura por diseño. Si el servidor está detrás de un reverse proxy que reenvía IP local o si la cabecera `Host` coincide, la bandera se activaría en el servidor en producción.
2. **Eliminación completa de `$isLocalEnv`, derivación estricta en servidor y bloqueo de propiedades sensibles (Opción Seleccionada):**
   - *Ventajas:*
     - Se elimina `$isLocalEnv` como propiedad pública del componente. El cliente no puede forzar su valor ni enviarlo en payloads de Livewire.
     - Se introduce el método de servidor `exposeDevOtp(): bool` que comprueba exclusivamente `app()->environment('local') && config('app.debug') === true`.
     - Se marcan con `#[Locked]` las propiedades de control de flujo y seguridad: `#[Locked] public string $step` y `#[Locked] public ?string $devOtpCode`.
     - Se purga el almacenamiento del código OTP en sesión (`dev_otp_code`).
     - Se actualiza la vista Blade para consultar `$this->exposeDevOtp()`.

---

## 3. Plan de Implementación

1. **Componente `Login` (`app/Livewire/Auth/Login.php`):**
   - Importar `Livewire\Attributes\Locked`.
   - Marcar `$step` y `$devOtpCode` con `#[Locked]`.
   - Eliminar `public bool $isLocalEnv`.
   - Implementar método público `exposeDevOtp(): bool` con validación estricta de entorno local y debug.
   - En `mount()`: eliminar la inicialización de `$isLocalEnv` y la lectura de `session('dev_otp_code')`.
   - En `requestOtp()`: condicionar la asignación de `$devOtpCode` a `if ($this->exposeDevOtp())`, eliminando `session(['dev_otp_code' => $code])`.
   - En `verifyOtp()` y `fillDevCode()`: aplicar salvaguardas equivalentes.

2. **Vista Blade (`resources/views/livewire/auth/login.blade.php`):**
   - Reemplazar la condición del banner de desarrollo por `@if($this->exposeDevOtp() && $devOtpCode)`.

3. **Pruebas de Regresión (`tests/Feature/PasswordlessAuthTest.php`):**
   - Actualizar el test existente de entorno local para simular `APP_ENV=local` y `APP_DEBUG=true`.
   - Añadir test que valide que en entorno de producción o testing sin debug (`APP_ENV=production`), `devOtpCode` es estrictamente `null` y no se expone a clientes.
   - Añadir test que verifique que intentar modificar propiedades bloqueadas (`step`, `devOtpCode`) o inyectar `isLocalEnv` falla y no permite obtener el código.
