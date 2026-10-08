<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los datos por entidad: todas las consultas se filtran por la entidad actual
 * y los registros nuevos se asignan a ella automáticamente.
 * Sin entidad actual (consola sin contexto) no se filtra: los comandos deben usar Organization::runFor().
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            if ($id = Organization::currentId()) {
                $query->where($query->getModel()->qualifyColumn('organization_id'), $id);
            }
        });

        static::creating(function ($model) {
            $model->organization_id ??= Organization::currentId();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Consulta sin el filtro de entidad (uso explícito y controlado). */
    public static function acrossOrganizations(): Builder
    {
        return static::query()->withoutGlobalScope('organization');
    }
}
