<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EvaluationStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Absent = 'ausente';
    case Published = 'publicada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Absent => 'No se presentó',
            self::Published => 'Publicada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Absent => 'gray',
            self::Published => 'green',
        };
    }
}
