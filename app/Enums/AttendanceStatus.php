<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttendanceStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Present = 'presente';
    case Absent = 'ausente';
    case Notified = 'aviso';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Present => 'Presente',
            self::Absent => 'Ausente',
            self::Notified => 'Avisó que falta',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Present => 'green',
            self::Absent => 'red',
            self::Notified => 'blue',
            self::Cancelled => 'gray',
        };
    }
}
