<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SubscriptionStatus: string
{
    use EnumHelpers;

    case Pending = 'pendiente';
    case Active = 'activa';
    case Expired = 'vencida';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::Active => 'Activa',
            self::Expired => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'yellow',
            self::Expired => 'gray',
            self::Cancelled => 'red',
        };
    }
}
