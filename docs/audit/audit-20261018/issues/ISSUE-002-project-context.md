# Project Context: [ISSUE-002] Blindaje contra Fuga de OTP y Manipulación de Estado en Login

- **Componente:** `jg_filmmaker`
- **Fecha de aplicación:** 18 de octubre de 2026
- **Estado del Issue:** Resuelto y Verificado
- **Severidad Original:** Crítica

---

## 1. Resumen de la Modificación
Se subsanó la vulnerabilidad crítica de bypass de autenticación por divulgación indebida de códigos OTP. Anteriormente, un atacante podía forzar la propiedad Livewire `isLocalEnv` o falsificar cabeceras `Host` para que el servidor le devolviera el PIN en texto plano en la respuesta de `requestOtp()`.

## 2. Cambios Arquitectónicos y de Seguridad
1. **Inmutabilidad y Bloqueo de Estado:**
   - Se eliminó la propiedad pública `isLocalEnv`.
   - Se marcaron las propiedades sensibles `$step` y `$devOtpCode` con el atributo `#[Locked]`.
2. **Derivación Estricta de Entorno:**
   - La comprobación de desarrollo se realiza mediante el método `Login::exposeDevOtp()`, que exige estrictamente que `app()->environment('local')` y `config('app.debug')` sean verdaderos simultáneamente. No se confía en cabeceras HTTP (`Host`, `X-Forwarded-*`) ni en IPs de cliente.
3. **Cero Retención en Sesión:**
   - Se suprimió la persistencia del código OTP en `session(['dev_otp_code' => ...])`.

## 3. Estado de Calidad y Pruebas
- Pruebas en `tests/Feature/PasswordlessAuthTest.php`:
  - `test_local_environment_exposes_dev_code_in_interface`
  - `test_production_environment_never_exposes_dev_code_to_client`
  - `test_locked_properties_cannot_be_mutated_by_client`
- 86 pruebas ejecutadas y aprobadas (522 aserciones). Pint y CI/CD en estado exitoso (0 fallos de formato y linters).
