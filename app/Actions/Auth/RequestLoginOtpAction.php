<?php

namespace App\Actions\Auth;

use App\Mail\LoginOtpMail;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class RequestLoginOtpAction
{
    /**
     * @return array{success: bool, code?: string, error?: string, field?: string}
     */
    public function execute(string $email, string $ip): array
    {
        $emailClean = strtolower(trim($email));

        $ipThrottleKey = 'request_otp_ip:'.$ip;
        $emailThrottleKey = 'request_otp_email:'.sha1($emailClean);

        if (RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);

            return [
                'success' => false,
                'field' => 'email',
                'error' => "Has superado el límite de solicitudes desde esta red. Por favor espera {$seconds} segundos antes de reintentar.",
            ];
        }

        if (RateLimiter::tooManyAttempts($emailThrottleKey, 3)) {
            $seconds = RateLimiter::availableIn($emailThrottleKey);

            return [
                'success' => false,
                'field' => 'email',
                'error' => "Has superado el límite de solicitudes para este correo. Por favor espera {$seconds} segundos antes de reintentar.",
            ];
        }

        RateLimiter::hit($ipThrottleKey, 600);
        RateLimiter::hit($emailThrottleKey, 600);

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

        OneTimePassword::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $raw = Str::upper(Str::random(8));
        $code = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
        $canonicalCode = strtoupper(str_replace([' ', '-'], '', $code));

        $codeHash = hash_hmac('sha256', $canonicalCode, (string) config('app.key'));

        OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => $codeHash,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'attempts' => 0,
        ]);

        try {
            Mail::to($emailClean)->send(new LoginOtpMail($code, 15));
        } catch (\Throwable $e) {
            logger()->error('Error enviando código OTP a '.$emailClean.': '.$e->getMessage());
        }

        return [
            'success' => true,
            'code' => $code,
        ];
    }
}
