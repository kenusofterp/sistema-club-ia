<?php

use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Las pantallas públicas de competencia usan canales públicos con el token como nombre
| (display.station.{token}, display.competition.{token}): no necesitan autorización.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Pedidos de modificación de notas: quienes gestionan la competencia o autorizan cambios en esa entidad.
Broadcast::channel('competition.{competitionId}.staff', function (User $user, int $competitionId) {
    $organizationId = Competition::acrossOrganizations()->whereKey($competitionId)->value('organization_id');

    return $organizationId && ($user->isSuperAdmin()
        || $user->hasPermissionIn('notas.autorizar', $organizationId)
        || $user->hasPermissionIn('competencias.gestionar', $organizationId));
});
