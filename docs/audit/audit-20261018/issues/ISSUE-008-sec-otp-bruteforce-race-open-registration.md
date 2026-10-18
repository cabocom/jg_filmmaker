# [ISSUE-008] Autenticación OTP: registro abierto, rate-limit solo por IP, doble uso por condición de carrera y coste CPU/almacenamiento no acotado

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Auth/Login.php:62-96,137-188`
- **Categoría:** Seguridad

## 1. Descripción del Problema
1. **Registro abierto + mail-bombing:** `User::firstOrCreate` crea un usuario `free` con cualquier email. El límite de 5 solicitudes/10 min es **solo por IP** (`request_otp:{ip}`): una botnet o rotación de IPs permite inundar de correos a una víctima y crear usuarios masivamente. No hay límite por email destino ni CAPTCHA.
2. **Fuerza bruta distribuida:** el límite de verificación es `ip + user_id` (5/min). Con N IPs el atacante tiene 5·N intentos/min contra un espacio de 36^8 ≈ 2,8·10¹². No hay contador **por cuenta** ni bloqueo progresivo, y los OTP no se invalidan tras fallos.
3. **Varios OTP válidos simultáneos:** cada solicitud crea una fila nueva y las anteriores siguen siendo válidas 15 minutos; la verificación itera **todos** los OTP históricos del usuario.
4. **DoS CPU:** por cada intento fallido se ejecutan hasta `3 × nº_OTP_históricos` `Hash::check` (bcrypt). No hay purga de `one_time_passwords` (crecimiento indefinido), por lo que el coste crece con el tiempo.
5. **Condición de carrera (TOCTOU):** `used_at` se comprueba en memoria y luego se hace `update`. Dos peticiones simultáneas con el mismo código pueden autenticar ambas (replay no detectado).
6. Un OTP ya usado/caducado que coincide registra `REUSED_OR_EXPIRED_TOKEN`, pero un OTP usado puede ocultar uno válido más nuevo con distinto hash: la lógica `break` en el primer match es no determinista respecto a validez.
7. `Auth::login($user, remember: true)` crea sesiones de 5 años (recaller) sin opt-in.

## 2. Evidencia de Código
```php
RateLimiter::tooManyAttempts('request_otp:'.$ip, 5);
$user = User::firstOrCreate(['email' => $emailClean], [... 'is_admin' => false]);
...
$otps = OneTimePassword::where('user_id', $user->id)->latest()->get();
foreach ($otps as $otp) {
    if (Hash::check($inputRaw, $otp->code_hash) || Hash::check($inputClean, ...) || Hash::check($inputHyphen, ...)) { ... }
}
...
$matchedOtp->update(['used_at' => now()]);   // no atómico
```

## 3. Impacto Operativo o de Seguridad
Toma de cuenta por fuerza bruta distribuida (viable contra objetivos de alto valor como el admin sembrado), abuso del SMTP (reputación/bloqueo del dominio), saturación de CPU y suplantación por doble uso.

## 4. Solución Recomendada
- Normalizar el código **una sola vez** y comparar contra **el último OTP activo** del usuario (invalidar los anteriores al emitir uno nuevo).
- Hash rápido con HMAC (el OTP es de alta entropía y corta vida): `hash_hmac('sha256', $code, config('app.key'))` + `hash_equals`.
- Consumo atómico: `OneTimePassword::whereKey($id)->whereNull('used_at')->where('expires_at','>',now())->update(['used_at'=>now()]) === 1`.
- Límites por **email** y por IP (`RateLimiter::for` con claves `sha1($email)`), bloqueo progresivo y máximo de 5 intentos por OTP (`attempts` en BD, invalidar al superarlo).
- Tarea programada `model:prune` para OTP caducados.
- Decidir si el registro abierto es deseable; si no, lista de invitación/allow-list.
- `remember` solo si el usuario lo marca.
