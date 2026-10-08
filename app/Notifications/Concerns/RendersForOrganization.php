<?php

namespace App\Notifications\Concerns;

use App\Models\Organization;

/**
 * Las notificaciones encoladas se procesan fuera de la petición: este trait restaura la entidad
 * para que nombre, logo y configuración del correo sean los del club o gimnasio correspondiente.
 */
trait RendersForOrganization
{
    public ?int $organizationId = null;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inOrganization(callable $callback): mixed
    {
        return $this->organizationId ? Organization::runFor($this->organizationId, fn () => $callback()) : $callback();
    }
}
