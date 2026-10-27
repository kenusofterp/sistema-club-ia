<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CompetitionStatus: string
{
    use EnumHelpers;

    case Draft = 'borrador';
    case InProgress = 'en_curso';
    case Finished = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InProgress => 'green',
            self::Finished => 'blue',
        };
    }
}
