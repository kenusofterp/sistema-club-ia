<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EnrollmentStatus: string
{
    use EnumHelpers;

    case Active = 'activa';
    case Ended = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Ended => 'Baja',
        };
    }

    public function color(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
