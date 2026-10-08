<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AccessResult: string
{
    use EnumHelpers;

    case Granted = 'permitido';
    case Denied = 'denegado';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Permitido',
            self::Denied => 'Denegado',
        };
    }

    public function color(): string
    {
        return $this === self::Granted ? 'green' : 'red';
    }
}
