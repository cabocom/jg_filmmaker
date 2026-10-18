<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                abort(401, 'No autenticado.');
            }

            return redirect()->route('login');
        }

        if (! Auth::user()->is_admin) {
            abort(403, 'Acceso denegado: Se requieren permisos de administrador.');
        }

        return $next($request);
    }
}
