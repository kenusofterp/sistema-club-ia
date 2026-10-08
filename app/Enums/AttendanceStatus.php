<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttendanceStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Present = 'presente';
    case Absent = 'ausente';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Present => 'Presente',
            self::Absent => 'Ausente',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Present => 'green',
            self::Absent => 'red',
            self::Cancelled => 'gray',
        };
    }
}
