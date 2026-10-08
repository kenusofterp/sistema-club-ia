<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo el personal del club (usuarios con un rol administrativo) accede al panel. */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->canAccessAdmin()) {
            if ($user?->canAccessPortal()) {
                return redirect()->route('portal.dashboard');
            }

            abort(403, 'No tiene acceso al panel de administración.');
        }

        return $next($request);
    }
}
