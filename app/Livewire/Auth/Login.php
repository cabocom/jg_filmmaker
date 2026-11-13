<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RequestLoginOtpAction;
use App\Actions\Auth\VerifyLoginOtpAction;
use Illuminate\Support\Facades\Auth;
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

    public function exposeDevOtp(): bool
    {
        return app()->environment('local') && (bool) config('app.debug');
    }

    public function requestOtp(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Introduce tu correo electrónico.',
            'email.email' => 'El formato del correo electrónico no es válido.',
        ]);

        $ip = request()->ip() ?? '127.0.0.1';
        $action = app(RequestLoginOtpAction::class);
        $result = $action->execute($this->email, $ip);

        if (! $result['success']) {
            $this->addError($result['field'] ?? 'email', $result['error'] ?? 'Error procesando solicitud OTP.');

            return;
        }

        if ($this->exposeDevOtp() && isset($result['code'])) {
            $this->devOtpCode = $result['code'];
        }

        $this->step = 'verify';
        $this->verification_code = '';
        $this->resetErrorBag();
    }

    public function verifyOtp(): void
    {
        $this->validate([
            'verification_code' => ['required', 'string'],
        ], [
            'verification_code.required' => 'Introduce el código de acceso recibido por correo.',
        ]);

        $ip = request()->ip() ?? '127.0.0.1';
        $userAgent = request()->userAgent();
        $action = app(VerifyLoginOtpAction::class);
        $result = $action->execute($this->email, $this->verification_code, $ip, $userAgent);

        if (! $result['success']) {
            $this->addError('verification_code', $result['error'] ?? 'Código inválido.');

            return;
        }

        Auth::login($result['user'], remember: $this->remember);
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
