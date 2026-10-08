<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Determina la entidad (club/gimnasio) de la petición:
 * - Panel de administración: la elegida en el selector, entre las que el usuario puede administrar.
 * - Portal del socio: la membresía elegida, entre las entidades donde la persona es socia.
 * - Web pública y API pública: por dominio / subdominio (o ?entidad=slug en la API).
 * Las acciones Livewire reutilizan el contexto de la página que las originó.
 */
class ResolveOrganization
{
    public const ADMIN_KEY = 'admin_organization_id';

    public const PORTAL_KEY = 'portal_organization_id';

    private const CONTEXT_KEY = 'context_organization_id';

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolve($request);

        Organization::setCurrent($organization);

        return $next($request);
    }

    private function resolve(Request $request): ?Organization
    {
        $route = (string) $request->route()?->getName();
        $hostOrganization = fn () => Organization::forHost($request->getHost());

        if (str_starts_with($route, 'api.')) {
            return $this->forApi($request, $hostOrganization);
        }

        $user = auth()->user();
        $hasSession = $request->hasSession();

        if ($user && str_starts_with($route, 'admin.')) {
            $organization = $this->pick($user->adminOrganizationIds(), $hasSession ? session(self::ADMIN_KEY) : null, $hostOrganization);
            $this->remember(self::ADMIN_KEY, $organization, $hasSession);

            return $organization;
        }

        if ($user && str_starts_with($route, 'portal.')) {
            $organization = $this->pick($user->membershipOrganizationIds(), $hasSession ? session(self::PORTAL_KEY) : null, $hostOrganization);
            $this->remember(self::PORTAL_KEY, $organization, $hasSession);

            return $organization;
        }

        // Peticiones sin ruta de página (p. ej. /livewire/update): contexto de la última página.
        if ($route === '' || str_starts_with($route, 'livewire.')) {
            if ($hasSession && ($id = session(self::CONTEXT_KEY)) && ($organization = Organization::find($id))) {
                return $organization;
            }

            return $hostOrganization();
        }

        $organization = $hostOrganization();
        if ($hasSession && $organization) {
            session([self::CONTEXT_KEY => $organization->id]);
        }

        return $organization;
    }

    /** Elige entre las entidades permitidas: la guardada en sesión, la del dominio o la primera. */
    private function pick(array $allowedIds, mixed $sessionId, Closure $hostOrganization): ?Organization
    {
        if ($allowedIds === []) {
            return $hostOrganization();
        }

        $id = in_array((int) $sessionId, $allowedIds, true) ? (int) $sessionId : null;

        if (! $id) {
            $host = $hostOrganization();
            $id = $host && in_array($host->id, $allowedIds, true) ? $host->id : $allowedIds[0];
        }

        return Organization::find($id);
    }

    private function remember(string $key, ?Organization $organization, bool $hasSession): void
    {
        if ($hasSession && $organization) {
            session([$key => $organization->id, self::CONTEXT_KEY => $organization->id]);
        }
    }

    private function forApi(Request $request, Closure $hostOrganization): ?Organization
    {
        $slug = $request->header('X-Entidad') ?: $request->query('entidad');
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            $allowed = array_values(array_unique([...$user->membershipOrganizationIds(), ...$user->adminOrganizationIds()]));
            $requested = $slug ? Organization::where('slug', $slug)->value('id') : null;

            return $this->pick($allowed, $requested, $hostOrganization);
        }

        return $slug ? Organization::where('slug', $slug)->where('is_active', true)->first() : $hostOrganization();
    }
}
