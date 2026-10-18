# Walkthrough de Implementación: ISSUE-008

## Resumen de Cambios Realizados

1. **Rate Limiting Dual Anti Mail-Bombing y Fuerza Bruta Distribuida:**
   - En `requestOtp`: se implementó rate limiting dual por IP (`request_otp_ip:`, máx 5 / 10 min) y por hash del correo destinatario (`request_otp_email:`, máx 3 / 10 min), impidiendo el uso de botnets para inundar casillas de correo o crear usuarios no deseados masivamente.
   - En `verifyOtp`: se implementó rate limiting dual por cuenta de usuario (`verify_otp_user:`, máx 5 / min) y por IP (`verify_otp_ip:`, máx 10 / min), mitigando ataques de fuerza bruta distribuidos.

2. **Unicidad e Invalidación Automática de Tokens:**
   - Al solicitar un nuevo OTP, cualquier código previo sin usar de dicho usuario se revoca de inmediato (`whereNull('used_at')->update(['used_at' => now()])`), asegurando que jamás existan múltiples OTPs activos simultáneamente.

3. **Criptografía Eficiente HMAC-SHA256 (O(1)):**
   - Se sustituyó bcrypt por HMAC-SHA256 con la clave de aplicación `config('app.key')`, verificado en tiempo constante con `hash_equals`.
   - Se erradica por completo el riesgo de denegación de servicio por saturación de CPU (DoS CPU) en bucles de comprobación. Se mantiene fallback seguro retrocompatible con bcrypt (`$2y$`).

4. **Consumo Atómico Anti-Race Condition (Anti-TOCTOU):**
   - El paso de token válido a token consumido se realiza mediante sentencia SQL atómica:
     `OneTimePassword::where('id', $latestOtp->id)->whereNull('used_at')->where('expires_at', '>', now())->update(['used_at' => now()]) === 1`.
   - Si dos peticiones simultáneas intentan canjear el mismo código, exactamente una tiene éxito y la segunda es rechazada registrándose en `security_breach_logs`.

5. **Límite de Intentos por Código (Anti-Bruteforce) y Telemetría:**
   - Creada la migración `2026_10_18_200000_add_attempts_to_one_time_passwords_table.php` para persistir la columna `attempts`.
   - Tras 5 intentos fallidos, el OTP es revocado inmediatamente y se registra un evento `OTP_MAX_ATTEMPTS_EXCEEDED` en `security_breach_logs`.

6. **Persistencia de Sesión Opt-In y Purga de Histórico:**
   - Añadida la propiedad `$remember` y checkbox en la vista Blade para que el usuario controle si desea recordar la sesión.
   - Trait `Prunable` en `OneTimePassword` con retención de 24 horas para mantenimiento automático mediante `php artisan model:prune`.

7. **Pruebas Automatizadas:**
   - 12 pruebas en `tests/Feature/PasswordlessAuthTest.php` cubriendo la invalidación de tokens previos, rate limiting por email de destino, revocación por 5 intentos erróneos, detección de replay/caducidad y consulta de depuración prunable.
