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

        // 1. Rate Limiting por IP para evitar spam de correos (Máx 5 cada 10 min)
        $ip = request()->ip() ?? '127.0.0.1';
        $throttleKey = 'request_otp:'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Has superado el límite de solicitudes. Por favor espera {$seconds} segundos antes de reintentar.");

            return;
        }
        RateLimiter::hit($throttleKey, 600);

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

        // 3. Generar código OTP seguro con formato XXXX-XXXX (8 caracteres alfanuméricos)
        $raw = Str::upper(Str::random(8));
        $code = substr($raw, 0, 4).'-'.substr($raw, 4, 4);

        // 4. Persistir hash en la base de datos con expiración de 15 minutos
        OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ]);

        // 5. Enviar por email
        try {
            Mail::to($emailClean)->send(new LoginOtpMail($code, 15));
        } catch (\Throwable $e) {
            logger()->error('Error enviando código OTP a '.$emailClean.': '.$e->getMessage());
        }

        // 6. Entorno local: exponer visualmente el código para agilizar desarrollo si corresponde
        if ($this->exposeDevOtp()) {
            $this->devOtpCode = $code;
        }

        $this->step = 'verify';
        $this->verification_code = '';
        $this->resetErrorBag();
    }

    /**
     * Paso 2: Valida el código OTP, detecta replay attacks e inicia sesión
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

        // Rate Limiting en la ruta de verificación (5 intentos por minuto)
        $verifyThrottleKey = 'verify_otp:'.(request()->ip() ?? '127.0.0.1').':'.$user->id;
        if (RateLimiter::tooManyAttempts($verifyThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($verifyThrottleKey);
            $this->addError('verification_code', "Demasiados intentos fallidos. Espera {$seconds} segundos antes de volver a intentar.");

            return;
        }
        RateLimiter::hit($verifyThrottleKey, 60);

        $inputRaw = Str::upper(trim($this->verification_code));
        $inputClean = str_replace([' ', '-'], '', $inputRaw);
        $inputHyphen = strlen($inputClean) === 8 ? substr($inputClean, 0, 4).'-'.substr($inputClean, 4, 4) : $inputRaw;

        // Comprobar historial de OTPs de este usuario
        $otps = OneTimePassword::where('user_id', $user->id)->latest()->get();
        $matchedOtp = null;

        foreach ($otps as $otp) {
            if (Hash::check($inputRaw, $otp->code_hash)
                || Hash::check($inputClean, $otp->code_hash)
                || Hash::check($inputHyphen, $otp->code_hash)) {
                $matchedOtp = $otp;
                break;
            }
        }

        if (! $matchedOtp) {
            $this->addError('verification_code', 'El código de acceso introducido no es correcto.');

            return;
        }

        // DETECCIÓN DE REPLAY ATTACKS Y TOKENS REVOCADOS / CADUCADOS
        if ($matchedOtp->used_at !== null || $matchedOtp->expires_at->isPast()) {
            SecurityBreachLog::create([
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'REUSED_OR_EXPIRED_TOKEN',
            ]);

            $this->addError('verification_code', 'Este código ya ha sido utilizado anteriormente o ha caducado por motivos de seguridad.');

            return;
        }

        // Token válido: marcar como usado y autenticar
        $matchedOtp->update(['used_at' => now()]);
        RateLimiter::clear($verifyThrottleKey);

        Auth::login($user, remember: true);
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
