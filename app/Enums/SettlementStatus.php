<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SettlementStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Confirmed = 'confirmada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por confirmar',
            self::Confirmed => 'Confirmada',
        };
    }

    public function color(): string
    {
        return $this === self::Confirmed ? 'green' : 'yellow';
    }
}
