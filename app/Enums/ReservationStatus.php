<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReservationStatus: string
{
    use EnumHelpers;

    case Confirmed = 'confirmada';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return $this === self::Confirmed ? 'green' : 'gray';
    }
}
