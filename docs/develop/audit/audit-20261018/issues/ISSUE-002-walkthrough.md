# Walkthrough Técnico: [ISSUE-002] Divulgación del OTP de login mediante propiedad Livewire manipulable (`isLocalEnv`) y cabecera `Host`: toma de cuenta (incl. admin)

- **Componente:** `jg_filmmaker`
- **Severidad:** Crítica
- **Fecha:** 18 de octubre de 2026

---

## 1. Archivos Modificados

### 1.1. Componente Livewire de Autenticación
- [`jg_filmmaker/app/Livewire/Auth/Login.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Auth/Login.php):
  - Se añade el atributo `#[Locked]` sobre las propiedades `public string $step` y `public ?string $devOtpCode`, impidiendo que el cliente las modifique vía payloads AJAX.
  - Se elimina definitivamente la propiedad pública `public bool $isLocalEnv`.
  - Se suprime la lógica insegura en `mount()` que evaluaba `request()->getHost()` y `request()->ip()`.
  - Se introduce el método `public function exposeDevOtp(): bool` que comprueba de forma exclusiva y estricta en servidor: `app()->environment('local') && (bool) config('app.debug')`.
  - En `requestOtp()`, la asignación de `$this->devOtpCode` queda condicionada a `$this->exposeDevOtp()`.
  - Se elimina el almacenamiento inseguro del OTP en sesión de desarrollo (`dev_otp_code`).
  - En `fillDevCode()`, se valida `$this->exposeDevOtp()` antes de autocompletar el código.

### 1.2. Plantilla Blade
- [`jg_filmmaker/resources/views/livewire/auth/login.blade.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/auth/login.blade.php):
  - Se sustituye `@if($devOtpCode && ($isLocalEnv || app()->environment('local', 'testing')))` por `@if($this->exposeDevOtp() && $devOtpCode)`.

### 1.3. Suite de Pruebas
- [`jg_filmmaker/tests/Feature/PasswordlessAuthTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/PasswordlessAuthTest.php):
  - Se adapta `test_local_environment_exposes_dev_code_in_interface()` para simular entorno local y debug activado.
  - Se añade `test_production_environment_never_exposes_dev_code_to_client()` para verificar que en producción/sin debug `devOtpCode` permanece en `null` y el banner no se renderiza.
  - Se añade `test_locked_properties_cannot_be_mutated_by_client()` para comprobar que cualquier intento de manipulación sobre `devOtpCode` dispara `CannotUpdateLockedPropertyException`.

---

## 2. Pruebas de Verificación y No Regresión

- **Linter de código:** `./vendor/bin/pint --test` (Passed)
- **Suite de pruebas:** `php artisan test` (86 passed, 522 assertions)
- **Pipeline CI/CD:** `./scripts/check.sh --cicd -p jg_filmmaker` (Passed)
