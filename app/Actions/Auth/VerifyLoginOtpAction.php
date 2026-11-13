<?php

namespace App\Actions\Auth;

use App\Models\OneTimePassword;
use App\Models\SecurityBreachLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class VerifyLoginOtpAction
{
    /**
     * @return array{success: bool, user?: User, error?: string}
     */
    public function execute(string $email, string $code, string $ip, ?string $userAgent): array
    {
        $emailClean = strtolower(trim($email));
        $user = User::where('email', $emailClean)->first();

        if (! $user) {
            return [
                'success' => false,
                'error' => 'No se ha encontrado ninguna solicitud de acceso para este correo electrónico.',
            ];
        }

        $userThrottleKey = 'verify_otp_user:'.$user->id;
        $ipThrottleKey = 'verify_otp_ip:'.$ip;

        if (RateLimiter::tooManyAttempts($userThrottleKey, 5) || RateLimiter::tooManyAttempts($ipThrottleKey, 10)) {
            $seconds = max(RateLimiter::availableIn($userThrottleKey), RateLimiter::availableIn($ipThrottleKey));

            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'RATE_LIMIT_EXCEEDED',
            ]);

            return [
                'success' => false,
                'error' => "Demasiados intentos fallidos de autenticación. Bloqueo temporal por seguridad: espera {$seconds} segundos.",
            ];
        }

        RateLimiter::hit($userThrottleKey, 900);
        RateLimiter::hit($ipThrottleKey, 900);

        $inputRaw = Str::upper(trim($code));
        $inputCanonical = str_replace([' ', '-'], '', $inputRaw);

        $latestOtp = OneTimePassword::where('user_id', $user->id)->latest('created_at')->first();

        if (! $latestOtp) {
            return [
                'success' => false,
                'error' => 'No hay ningún código de acceso pendiente. Por favor solicita uno nuevo.',
            ];
        }

        if ($latestOtp->used_at !== null || $latestOtp->expires_at->isPast()) {
            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'REUSED_OR_EXPIRED_TOKEN',
            ]);

            return [
                'success' => false,
                'error' => 'Este código ya ha sido utilizado anteriormente o ha caducado por motivos de seguridad.',
            ];
        }

        if ($latestOtp->attempts >= 5) {
            $latestOtp->update(['used_at' => now()]);

            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
            ]);

            return [
                'success' => false,
                'error' => 'Este código ha sido bloqueado tras superar el número máximo de intentos fallidos permitidos.',
            ];
        }

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
                    'user_agent' => $userAgent,
                    'user_id' => $user->id,
                    'attempted_at' => now(),
                    'reason' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
                ]);

                return [
                    'success' => false,
                    'error' => 'El código es incorrecto. Has superado el límite de 5 intentos y el código ha sido revocado.',
                ];
            }

            $remaining = 5 - $latestOtp->attempts;

            return [
                'success' => false,
                'error' => "El código de acceso introducido no es correcto. Intentos restantes: {$remaining}.",
            ];
        }

        $consumed = OneTimePassword::where('id', $latestOtp->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($consumed !== 1) {
            SecurityBreachLog::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'user_id' => $user->id,
                'attempted_at' => now(),
                'reason' => 'REUSED_OR_EXPIRED_TOKEN',
            ]);

            return [
                'success' => false,
                'error' => 'Este código ya ha sido utilizado anteriormente o ha caducado por motivos de seguridad.',
            ];
        }

        RateLimiter::clear($userThrottleKey);
        RateLimiter::clear($ipThrottleKey);

        return [
            'success' => true,
            'user' => $user,
        ];
    }
}
