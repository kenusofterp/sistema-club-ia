<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PlanAccessType: string
{
    use EnumHelpers;

    case Free = 'libre';
    case Classes = 'clases';
    case FreeAndClasses = 'libre_y_clases';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Acceso libre (sala / aparatos)',
            self::Classes => 'Solo clases incluidas',
            self::FreeAndClasses => 'Acceso libre + clases incluidas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Free => 'blue',
            self::Classes => 'yellow',
            self::FreeAndClasses => 'green',
        };
    }

    public function allowsFreeAccess(): bool
    {
        return $this !== self::Classes;
    }

    public function includesClasses(): bool
    {
        return $this !== self::Free;
    }
}
