<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El portal es exclusivo de usuarios vinculados a un socio. */
class EnsureMemberAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->canAccessPortal()) {
            if ($user?->canAccessAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            abort(403, 'Su usuario no está vinculado a un socio.');
        }

        return $next($request);
    }
}
