<?php

namespace App\Console\Commands\Concerns;

use App\Models\Organization;

/** Ejecuta la tarea en el contexto de cada entidad activa (con su propia configuración). */
trait RunsForEachOrganization
{
    /** @param  callable(Organization): void  $callback */
    protected function forEachOrganization(callable $callback): void
    {
        Organization::query()->where('is_active', true)->orderBy('id')->each(
            fn (Organization $organization) => Organization::runFor($organization, $callback)
        );
    }
}
