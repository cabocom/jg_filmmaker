<?php

namespace App\Livewire\Auth;

use App\Mail\LoginOtpMail;
use App\Models\OneTimePassword;
use App\Models\SecurityBreachLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Acceso Seguro - jG Filmmaker')]
class Login extends Component
{
    public string $email = '';

    public string $verification_code = '';

    public bool $remember = false;

    #[Locked]
    public string $step = 'request'; // 'request' | 'verify'

    #[Locked]
    public ?string $devOtpCode = null;

    public function mount(): void
    {
        if (Auth::check()) {
            redirect()->route('projects.index');
        }
    }

    /**
     * Determina si se permite exponer el código OTP para facilitar el desarrollo local.
     * Solo activo si el entorno es estrictamente 'local' y APP_DEBUG está activado.
     */
    public function exposeDevOtp(): bool
    {
        return app()->environment('local') && (bool) config('app.debug');
    }

    /**
     * Paso 1: Valida el correo y genera el código OTP
     */
    public function requestOtp(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Introduce tu correo electrónico.',
            'email.email' => 'El formato del correo electrónico no es válido.',
        ]);

        $emailClean = strtolower(trim($this->email));
        $ip = request()->ip() ?? '127.0.0.1';

        // 1. Rate Limiting dual: por IP (máx 5 / 10 min) y por Email destino (máx 3 / 10 min) para mitigar mail-bombing
        $ipThrottleKey = 'request_otp_ip:'.$ip;
        $emailThrottleKey = 'request_otp_email:'.sha1($emailClean);

        if (RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);
            $this->addError('email', "Has superado el límite de solicitudes desde esta red. Por favor espera {$seconds} segundos antes de reintentar.");

            return;
        }

        if (RateLimiter::tooManyAttempts($emailThrottleKey, 3)) {
            $seconds = RateLimiter::availableIn($emailThrottleKey);
            $this->addError('email', "Has superado el límite de solicitudes para este correo. Por favor espera {$seconds} segundos antes de reintentar.");

            return;
        }

        RateLimiter::hit($ipThrottleKey, 600);
        RateLimiter::hit($emailThrottleKey, 600);

        // 2. Obtener o crear el usuario
        $user = User::firstOrCreate(
            ['email' => $emailClean],
            [
                'name' => Str::title(Str::before($emailClean, '@')),
                'password' => Hash::make(Str::random(32)),
                'plan_tier' => 'free',
                'paid_project_slots' => 5,
                'is_admin' => false,
            ]
        );

        // 3. Invalidar de inmediato cualquier OTP previo no utilizado de este usuario (unicidad de OTP activo)
        OneTimePassword::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // 4. Generar código OTP seguro con formato XXXX-XXXX (8 caracteres alfanuméricos)
        $raw = Str::upper(Str::random(8));
        $code = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
        $canonicalCode = strtoupper(str_replace([' ', '-'], '', $code));

        // 5. Hash rápido y resistente a DoS CPU mediante HMAC-SHA256 con la clave de la aplicación
        $codeHash = hash_hmac('sha256', $canonicalCode, (string) config('app.key'));

        OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => $codeHash,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'attempts' => 0,
        ]);

        // 6. Enviar por email
        try {
            Mail::to($emailClean)->send(new LoginOtpMail($code, 15));
        } catch (\Throwable $e) {
            logger()->error('Error enviando código OTP a '.$emailClean.': '.$e->getMessage());
        }

        // 7. Entorno local: exponer visualmente el código para agilizar desarrollo si corresponde
        if ($this->exposeDevOtp()) {
            $this->devOtpCode = $code;
        }

        $this->step = 'verify';
        $this->verification_code = '';
        $this->resetErrorBag();
    }

    /**
     * Paso 2: Valida el código OTP de forma atómica, detecta replay attacks e inicia sesión
     */
    public function verifyOtp(): void
    {
        $this->validate([
            'verification_code' => ['required', 'string', 'min:6', 'max:12'],
        ], [
            'verification_code.required' => 'Introduce el código que hemos enviado a tu correo.',
        ]);

        $emailClean = strtolower(trim($this->email));
        $user = User::where('email', $emailClean)->first();

        if (! $user) {
            $this->addError('verification_code', 'Usuario no encontrado. Por favor solicita un nuevo código.');
            $this->step = 'request';

            return;
        }

        $ip = request()->ip() ?? '127.0.0.1';

        // Rate Limiting dual en verificación: por cuenta de usuario (5/min) y por IP (10/min) contra fuerza bruta distribuida
        $userThrottleKey = 'verify_otp_user:'.$user->id;
        $ipThrottleKey = 'verify_otp_ip:'.$ip;

        if (RateLimiter::tooManyAttempts($userThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($userThrottleKey);
            $this->addError('verification_code', "Demasiados intentos fallidos para esta cuenta. Espera {$seconds} segundos antes de volver a intentar.");

            return;
        }

        if (RateLimiter::tooManyAttempts($ipThrottleKey, 10)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);
            $this->addError('verification_code', "Demasiados intentos desde esta red. Espera {$seconds} segundos antes de volver a intentar.");

            return;
        }

        RateLimiter::hit($userThrottleKey, 60);
        RateLimiter::hit($ipThrottleKey, 60);

        $inputRaw = Str::upper(trim($this->verification_code));
        $inputCanonical = str_replace([' ', '-'], '', $inputRaw);

        // Obtener el último OTP registrado para este usuario
        $latestOtp = OneTimePassword::where('user_id', $user->id)->latest('created_at')->first();

        if (! $latestOtp) {
            $this->addError('verification_code', 'No hay ningún código de acceso pendiente. Por favor solicita uno nuevo.');

            return;
        }

        // Detección de replay attack o código caducado
        if ($latestOtp->used_at !== null || $latestOtp->expires_at->isPast()) {
            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'REUSED_OR_EXPIRED_TOKEN',
            ]);

            $this->addError('verification_code', 'Este código ya ha sido utilizado anteriormente o ha caducado por motivos de seguridad.');

            return;
        }

        // Límite de intentos erróneos por código OTP (máximo 5 intentos)
        if ($latestOtp->attempts >= 5) {
            $latestOtp->update(['used_at' => now()]);

            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
            ]);

            $this->addError('verification_code', 'Este código ha sido bloqueado tras superar el número máximo de intentos fallidos permitidos.');

            return;
        }

        // Comprobación de integridad con HMAC-SHA256 (y fallback retrocompatible con bcrypt si existiera)
        $expectedHash = hash_hmac('sha256', $inputCanonical, (string) config('app.key'));
        $isMatch = hash_equals($latestOtp->code_hash, $expectedHash);

        if (! $isMatch && str_starts_with($latestOtp->code_hash, '$2y$')) {
            $inputHyphen = strlen($inputCanonical) === 8 ? substr($inputCanonical, 0, 4).'-'.substr($inputCanonical, 4, 4) : $inputRaw;
            $isMatch = Hash::check($inputRaw, $latestOtp->code_hash)
                || Hash::check($inputCanonical, $latestOtp->code_hash)
                || Hash::check($inputHyphen, $latestOtp->code_hash);
        }

        if (! $isMatch) {
            $latestOtp->increment('attempts');

            if ($latestOtp->attempts >= 5) {
                $latestOtp->update(['used_at' => now()]);

                SecurityBreachLog::create([
                    'ip_address' => $ip,
                    'user_agent' => request()->userAgent(),
                    'user_id' => $user->id,
                    'attempted_at' => now(),
                    'reason' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
                ]);

                $this->addError('verification_code', 'El código es incorrecto. Has superado el límite de 5 intentos y el código ha sido revocado.');

                return;
            }

            $remaining = 5 - $latestOtp->attempts;
            $this->addError('verification_code', "El código de acceso introducido no es correcto. Intentos restantes: {$remaining}.");

            return;
        }

        // CONSUMO ATÓMICO EN BD (Anti-TOCTOU / Anti-Race Condition):
        // Solo un proceso concurrente puede actualizar used_at de null a now()
        $consumed = OneTimePassword::where('id', $latestOtp->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($consumed !== 1) {
            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'REUSED_OR_EXPIRED_TOKEN',
            ]);

            $this->addError('verification_code', 'Este código ya ha sido utilizado anteriormente o ha caducado por motivos de seguridad.');

            return;
        }

        RateLimiter::clear($userThrottleKey);
        RateLimiter::clear($ipThrottleKey);

        Auth::login($user, remember: $this->remember);
        session()->regenerate();

        redirect()->intended(route('projects.index'));
    }

    public function fillDevCode(): void
    {
        if ($this->exposeDevOtp() && $this->devOtpCode) {
            $this->verification_code = $this->devOtpCode;
        }
    }

    public function changeEmail(): void
    {
        $this->step = 'request';
        $this->verification_code = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
