<?php

namespace App\Livewire\Concerns;

use App\Enums\MemberStatus;
use App\Models\Person;
use Illuminate\Support\Collection;

/** Búsqueda de alumnos en la base única de personas del sistema (todas las entidades). */
trait SearchesPeople
{
    /**
     * Por cada persona devuelve su membresía activa en la entidad indicada o, si no la tiene, la de otra
     * entidad (al usarla se le crea la membresía en esta). Solo expone nombre y documento.
     *
     * @return Collection<int, array{id: int, person_id: int, name: string, detail: string}>
     */
    protected function searchPeople(string $term, ?int $organizationId, int $limit = 8): Collection
    {
        if (mb_strlen(trim($term)) < 2) {
            return collect();
        }

        return Person::search($term)
            ->whereHas('memberships', fn ($q) => $q->where('status', MemberStatus::Active))
            ->with(['memberships' => fn ($q) => $q->where('status', MemberStatus::Active)->with('organization')])
            ->orderBy('last_name')->orderBy('first_name')
            ->limit($limit)
            ->get()
            ->map(function (Person $person) use ($organizationId) {
                $membership = $person->memberships->firstWhere('organization_id', $organizationId) ?? $person->memberships->first();

                return [
                    'id' => $membership->id,
                    'person_id' => $person->id,
                    'name' => "{$person->last_name}, {$person->first_name}",
                    'detail' => "{$person->document_type} {$person->document_number}"
                        .($membership->organization_id !== $organizationId ? ' · socio/a de '.$membership->organization->name : ''),
                ];
            });
    }
}
