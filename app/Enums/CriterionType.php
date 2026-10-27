<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/** Cómo aporta un criterio al total del juez: puntaje suma, deducción resta, sí/no suma el peso si es «sí». */
enum CriterionType: string
{
    use EnumHelpers;

    case Score = 'puntaje';
    case Deduction = 'deduccion';
    case YesNo = 'si_no';

    public function label(): string
    {
        return match ($this) {
            self::Score => 'Puntaje',
            self::Deduction => 'Deducción',
            self::YesNo => 'Sí / No',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Score => 'green',
            self::Deduction => 'red',
            self::YesNo => 'blue',
        };
    }
}
