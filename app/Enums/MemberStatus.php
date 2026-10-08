<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MemberStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Active = 'activo';
    case Suspended = 'suspendido';
    case Inactive = 'baja';
    case Rejected = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de aprobación',
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Inactive => 'Baja',
            self::Rejected => 'Rechazado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'yellow',
            self::Suspended, self::Rejected => 'red',
            self::Inactive => 'gray',
        };
    }
}
