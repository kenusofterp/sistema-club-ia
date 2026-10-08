<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReceiptStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Approved = 'aprobado';
    case Rejected = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Approved => 'Acreditado',
            self::Rejected => 'Rechazado',
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
