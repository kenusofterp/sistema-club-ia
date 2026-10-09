<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FeeType: string
{
    use EnumHelpers;

    case Membership = 'social';
    case Activity = 'actividad';
    case Admission = 'ingreso';
    case Reservation = 'reserva';
    case Plan = 'plan';
    case Lesson = 'clase';
    case Registration = 'inscripcion';
    case Tournament = 'torneo';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Membership => 'Cuota social',
            self::Activity => 'Actividad',
            self::Admission => 'Derecho de ingreso',
            self::Reservation => 'Reserva de instalación',
            self::Plan => 'Plan / membresía',
            self::Lesson => 'Clase suelta',
            self::Registration => 'Inscripción anual',
            self::Tournament => 'Torneo',
            self::Other => 'Otro cargo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Membership, self::Registration => 'blue',
            self::Tournament => 'yellow',
            self::Activity, self::Plan, self::Lesson => 'green',
            default => 'gray',
        };
    }
}
