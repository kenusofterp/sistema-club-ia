<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureGymMode;
use App\Http\Middleware\EnsureMemberAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Windows: OpenSSL necesita su archivo de configuración para generar las claves EC del cifrado de las push.
        if (PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF') && is_file($conf = dirname(PHP_BINARY).'\\extras\\ssl\\openssl.cnf')) {
            putenv("OPENSSL_CONF={$conf}");
        }

        // Layouts usables como <x-layouts::site> y como layout de páginas Livewire.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
        View::addNamespace('layouts', resource_path('views/layouts'));

        // Entidad actual disponible en todas las vistas.
        View::composer('*', fn ($view) => $view->with('currentOrganization', Organization::current()));

        // Las acciones de componentes Livewire respetan los mismos middleware que la ruta.
        Livewire::addPersistentMiddleware([ResolveOrganization::class, EnsureUserIsActive::class, EnsureAdminAccess::class, EnsureMemberAccess::class, EnsureGymMode::class]);

        // El super administrador tiene todos los permisos.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Gestión de la plataforma (alta de entidades): exclusivo del super administrador (vía Gate::before).
        Gate::define('plataforma', fn (User $user) => false);

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        $this->registerAuthAuditing();
    }

    /** Auditoría de inicios de sesión, cierres e intentos fallidos. */
    private function registerAuthAuditing(): void
    {
        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            activity('auth')->causedBy($event->user)->event('login')
                ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
                ->log('Inicio de sesión');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                activity('auth')->causedBy($event->user)->event('logout')->log('Cierre de sesión');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            activity('auth')->event('failed')
                ->withProperties(['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])
                ->log('Intento de inicio de sesión fallido');
        });
    }
}
