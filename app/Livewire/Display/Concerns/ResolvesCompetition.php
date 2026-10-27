<?php

namespace App\Livewire\Display\Concerns;

use App\Models\Competition;
use App\Models\Organization;
use Livewire\Attributes\Locked;

/**
 * Pantallas públicas: la entidad sale de la competencia (no del dominio ni de la sesión). Se vuelve a
 * fijar en cada petición de Livewire, porque el middleware solo conoce el dominio.
 */
trait ResolvesCompetition
{
    #[Locked]
    public int $competitionId;

    public function hydrateResolvesCompetition(): void
    {
        $this->useOrganizationOf($this->competitionId);
    }

    protected function useOrganizationOf(int $competitionId): Competition
    {
        $competition = Competition::acrossOrganizations()->with('organization')->findOrFail($competitionId);
        Organization::setCurrent($competition->organization);

        return $competition;
    }
}
