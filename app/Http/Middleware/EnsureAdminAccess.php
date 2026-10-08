<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo el personal del club (usuarios con un rol administrativo) accede al panel. */
class EnsureAdminAccess
{
    /** Pantallas disponibles cuando todavía no hay ninguna entidad activa (instalación desde cero). */
    private const WITHOUT_ORGANIZATION = ['admin.organizations', 'admin.profile', 'admin.help'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->canAccessAdmin()) {
            if ($user?->canAccessPortal()) {
                return redirect()->route('portal.dashboard');
            }

            abort(403, 'No tiene acceso al panel de administración.');
        }

        if (! Organization::current() && ! $request->routeIs(self::WITHOUT_ORGANIZATION)) {
            return redirect()->route('admin.organizations');
        }

        return $next($request);
    }
}
