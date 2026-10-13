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
    // Se paga con el saldo a favor del socio (ej. dinero de un torneo cancelado que quedó a cuenta).
    case AccountCredit = 'saldo';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::DebitCard => 'Tarjeta de débito',
            self::CreditCard => 'Tarjeta de crédito',
            self::Other => 'Otro',
            self::AccountCredit => 'Saldo a favor',
        };
    }

    /** Medios que se eligen a mano al cobrar o devolver (el saldo a favor se ofrece aparte, si hay). */
    public static function manualOptions(): array
    {
        return array_diff_key(self::options(), [self::AccountCredit->value => true]);
    }

    public function color(): string
    {
        return 'gray';
    }
}
