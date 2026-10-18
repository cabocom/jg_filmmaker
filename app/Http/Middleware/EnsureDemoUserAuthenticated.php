<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureDemoUserAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // El autologin demo queda estrictamente confinado a entorno local si está habilitado explícitamente.
        // Nunca debe ejecutarse en producción, testing ni enmascarar la suite de pruebas.
        if (app()->environment('local') && config('app.demo_autologin', false) === true && ! Auth::check() && ! $request->is('login*', 'logout*')) {
            try {
                if (Schema::hasTable('users')) {
                    $user = User::firstOrCreate(
                        ['email' => 'filmmaker@cabocom.net'],
                        [
                            'name' => 'Javier Gálvez',
                            'password' => Hash::make(bin2hex(random_bytes(16))),
                            'plan_tier' => 'pro',
                            'paid_project_slots' => 5,
                            'is_admin' => false,
                        ]
                    );

                    Auth::login($user);
                }
            } catch (Throwable) {
                // Continuar sin auto-login si la BD no está disponible
            }
        }

        return $next($request);
    }
}
