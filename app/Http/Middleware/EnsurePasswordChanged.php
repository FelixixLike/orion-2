<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $guard = null): Response
    {
        $guard = $guard ?? 'web';

        $user = Auth::guard($guard)->user();

        // Si no hay usuario autenticado, redirigir al login correspondiente.
        if (! $user) {
            return $guard === 'admin'
                ? redirect()->route('admin.login')
                : redirect()->route('portal.login');
        }

        // Si el usuario debe cambiar su contraseña.
        if ($user->must_change_password ?? false) {
            $excludedRoutes = [
                'password.force-change.show',
                'password.force-change.post',
                'admin.logout',
                'portal.logout',
                'logout',
            ];

            if (! $request->routeIs($excludedRoutes)) {
                return redirect()->route('password.force-change.show');
            }
        }

        return $next($request);
    }
}
