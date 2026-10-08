<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum LessonStatus: string
{
    use EnumHelpers;

    case Scheduled = 'programada';
    case Given = 'dada';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::Given => 'Dada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Given => 'green',
            self::Cancelled => 'gray',
        };
    }
}
