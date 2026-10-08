<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureGymMode;
use App\Http\Middleware\EnsureMemberAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'member' => EnsureMemberAccess::class,
            'active' => EnsureUserIsActive::class,
            'gym' => EnsureGymMode::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Entidad actual (club/gimnasio): después de iniciar sesión y antes del route model binding,
        // para que los modelos de la URL se resuelvan solo dentro de la entidad.
        $middleware->web(append: [ResolveOrganization::class]);
        $middleware->api(append: [ResolveOrganization::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveOrganization::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Las reglas de negocio violadas son errores del usuario, no del sistema: no se reportan al log.
        $exceptions->dontReport(BusinessRuleException::class);

        // En la API se devuelven como 422.
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    })->create();
