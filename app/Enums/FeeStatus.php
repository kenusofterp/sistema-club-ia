<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum FeeStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Partial = 'parcial';
    case Overdue = 'vencida';
    case Paid = 'pagada';
    case Cancelled = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Partial => 'Pago parcial',
            self::Overdue => 'Vencida',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Pending => 'blue',
            self::Partial => 'yellow',
            self::Overdue => 'red',
            self::Cancelled => 'gray',
        };
    }

    /** Estados con saldo exigible. */
    public static function open(): array
    {
        return [self::Pending->value, self::Partial->value, self::Overdue->value];
    }
}
