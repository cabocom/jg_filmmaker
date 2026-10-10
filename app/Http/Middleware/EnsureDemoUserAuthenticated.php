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
        // Emulación de demo user activa exclusivamente en entorno testing (fuera de rutas de login)
        if (app()->environment('testing') && ! Auth::check() && ! $request->is('login*', 'logout*')) {
            try {
                if (Schema::hasTable('users')) {
                    $user = User::firstOrCreate(
                        ['email' => 'filmmaker@cabocom.net'],
                        [
                            'name' => 'Javier Gálvez',
                            'password' => Hash::make('secret1234'),
                            'plan_tier' => 'pro',
                            'paid_project_slots' => 5,
                            'is_admin' => true,
                        ]
                    );

                    Auth::login($user);
                }
            } catch (Throwable) {
                // Si la base de datos no está migrada aún en ciertas pruebas, continuar sin auto-login
            }
        }

        return $next($request);
    }
}
