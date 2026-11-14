# Contexto de Proyecto Actualizado: ISSUE-008

## Componentes y Arquitectura Modificada

### Autenticación Passwordless (`jg_filmmaker/app/Livewire/Auth/Login.php`)
- El componente `Login` ahora aplica gobernanza de seguridad estricta en dos niveles:
  - Mitigación de mail-bombing y abusos de registro mediante limitadores de tasa paralelos: por IP y por correo destino.
  - Mitigación de fuerza bruta distribuida mediante limitador por cuenta de usuario (`verify_otp_user:`) complementado con límite por IP.
- El hashing de los códigos OTP migró de bcrypt a HMAC-SHA256 con `config('app.key')`, permitiendo comprobaciones en tiempo constante O(1) e inmunes a ataques de agotamiento de CPU.
- El consumo de tokens se realiza de forma atómica en base de datos (`update WHERE used_at IS NULL AND expires_at > now()`), neutralizando condiciones de carrera de uso concurrente (replay attack).
- Límite de 5 intentos fallidos por código tras el cual se revoca y se audita como `OTP_MAX_ATTEMPTS_EXCEEDED` en `security_breach_logs`.
- Sesiones de larga duración (`remember: true`) convertidas a opt-in voluntario mediante checkbox.

### Modelo y Esquema de Base de Datos (`OneTimePassword`)
- Nueva columna `attempts` en la tabla `one_time_passwords`.
- Trait `Prunable` para purgar registros con más de 24 horas (`artisan model:prune`).

## Métricas de Calidad
- 157 tests pasando al 100% en PHPUnit (12 específicos de autenticación).
- 0 incidencias en Laravel Pint.
