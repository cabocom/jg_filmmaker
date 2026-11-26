<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class IframeSsoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $secret = (string) config('services.jg_agente.secret', '');
        if ($secret === '') {
            abort(403, 'SSO secret not configured.');
        }

        $email = (string) $request->query('email', '');
        $expRaw = (string) $request->query('exp', '');
        $nonce = (string) $request->query('nonce', '');
        $redirect = (string) $request->query('redirect', '');
        $sig = (string) $request->query('sig', '');

        if ($email === '' || $expRaw === '' || $nonce === '' || strlen($nonce) > 128 || $redirect === '' || $sig === '' || ! ctype_digit($expRaw)) {
            abort(403, 'Invalid SSO parameters.');
        }

        $exp = (int) $expRaw;
        $now = time();
        if ($exp < $now || $exp > $now + 120) {
            abort(403, 'SSO ticket expired.');
        }

        if (preg_match('#^/(tv|projects)(/[A-Za-z0-9\-_]*)?(\?embed=1)?$#', $redirect) !== 1) {
            abort(403, 'Invalid SSO redirect target.');
        }

        $payload = "{$email}|{$expRaw}|{$nonce}|{$redirect}";
        $expectedSig = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSig, $sig)) {
            abort(403, 'Invalid SSO signature.');
        }

        if (! Cache::add("iframe_sso_nonce:{$nonce}", true, 120)) {
            abort(403, 'SSO nonce already used.');
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_admin', true)
            ->first();

        if ($user === null) {
            abort(403, 'Unauthorized SSO user.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($redirect);
    }
}
