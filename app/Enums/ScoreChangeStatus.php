<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ScoreChangeStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Approved = 'aprobada';
    case Rejected = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
