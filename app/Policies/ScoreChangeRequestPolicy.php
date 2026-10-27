<?php

namespace App\Policies;

use App\Models\ScoreChangeRequest;
use App\Models\User;

/**
 * Control de 4 ojos: autoriza alguien con notas.autorizar que no sea quien pidió el cambio.
 * El super administrador (Gate::before) puede autoaprobar para no bloquear el torneo.
 */
class ScoreChangeRequestPolicy
{
    public function resolve(User $user, ScoreChangeRequest $request): bool
    {
        return $user->can('notas.autorizar') && $user->id !== $request->requested_by;
    }
}
