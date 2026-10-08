<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentStatus: string
{
    use EnumHelpers;

    case Confirmed = 'confirmado';
    case Cancelled = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return $this === self::Confirmed ? 'green' : 'red';
    }
}
