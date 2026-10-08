<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los módulos de gimnasio solo existen en modo "gimnasio" o "mixto". */
class EnsureGymMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(uses_gym(), 404);

        return $next($request);
    }
}
