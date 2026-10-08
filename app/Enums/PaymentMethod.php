<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentMethod: string
{
    use EnumHelpers;

    case Cash = 'efectivo';
    case Transfer = 'transferencia';
    case DebitCard = 'debito';
    case CreditCard = 'credito';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::DebitCard => 'Tarjeta de débito',
            self::CreditCard => 'Tarjeta de crédito',
            self::Other => 'Otro',
        };
    }

    public function color(): string
    {
        return 'gray';
    }
}
