# Análisis de Resolución: ISSUE-008

## 1. Contexto y Diagnóstico
La auditoría de seguridad reveló deficiencias críticas en el flujo de autenticación passwordless OTP en `App\Livewire\Auth\Login`:
1. **Mail-Bombing y Registro Abierto no acotado:**
   El rate-limit solo operaba por IP del cliente (`request_otp:{ip}`). Un atacante con IPs distribuidas o rotación de proxies podía generar usuarios indiscriminadamente e inundar de correos electrónicos a cualquier víctima.
2. **Fuerza Bruta Distribuida:**
   El límite de verificación dependía de `ip + user_id`, permitiendo ataques distribuidos contra una cuenta específica sin bloqueo a nivel de usuario ni invalidación por intentos fallidos.
3. **Múltiples OTPs Activos e Ineficiencia CPU:**
   Cada solicitud generaba un nuevo OTP sin revocar los previos. La verificación iteraba sobre todos los OTPs históricos ejecutando múltiples llamadas costosas a `Hash::check` (bcrypt), lo que abría una vía de denegación de servicio (DoS) por consumo de CPU.
4. **Condición de Carrera (TOCTOU):**
   La comprobación de `used_at !== null` se realizaba en memoria antes de la llamada a `update(['used_at' => now()])`, permitiendo que dos peticiones concurrentes consumieran el mismo código sin detección de replay.
5. **Persistencia de Sesión Indiscriminada:**
   `Auth::login($user, remember: true)` creaba tokens de sesión de larga duración sin consentimiento explícito del usuario.

## 2. Decisiones de Diseño
- **Rate-Limiting Dual:**
  - Emisión de OTP (`requestOtp`): Límite por IP (5 cada 10 min) Y límite estricto por Email destino (`request_otp_email:{sha1}`, 3 cada 10 min).
  - Verificación de OTP (`verifyOtp`): Límite por cuenta (`verify_otp_user:{user_id}`, 5 por minuto) Y por IP (10 por minuto).
- **Unicidad e Invalidación de Tokens:**
  - Al emitir un nuevo OTP, todos los OTPs no usados previos del usuario se marcan automáticamente como revocados/usados (`whereNull('used_at')->update(['used_at' => now()])`).
- **Hashing HMAC SHA-256 de Alta Eficiencia (O(1)):**
  - Normalización canónica de los códigos (`XXXX-XXXX` -> 8 caracteres en mayúsculas sin guiones ni espacios).
  - Sustitución de bcrypt por `hash_hmac('sha256', $canonical, config('app.key'))`, verificado en tiempo constante con `hash_equals` (con fallback de retrocompatibilidad para hashes bcrypt preexistentes).
- **Consumo Atómico en Base de Datos:**
  - La transición de validez a consumido se ejecuta en una única sentencia SQL atómica:
    `OneTimePassword::where('id', $id)->whereNull('used_at')->where('expires_at', '>', now())->update(['used_at' => now()]) === 1`.
- **Límite de Intentos por Código y Telemetría de Seguridad:**
  - Adición del campo `attempts` en la tabla `one_time_passwords`.
  - Si un código acumula 5 intentos erróneos, queda revocado de inmediato y se registra un evento `OTP_MAX_ATTEMPTS_EXCEEDED` en `security_breach_logs`.
- **Remember Me Opt-In:**
  - Propiedad Livewire `public bool $remember = false;` y checkbox opcional en la interfaz de usuario.
- **Mantenimiento y Purga Automática:**
  - Trait `Prunable` en el modelo `OneTimePassword` para eliminar registros de más de 24 horas mediante `artisan model:prune`.
