<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/** Tipos de campo de la ficha médica. */
enum MedicalFieldType: string
{
    use EnumHelpers;

    case Text = 'texto';
    case Textarea = 'texto_largo';
    case YesNo = 'si_no';
    case Select = 'opcion';
    case Checkboxes = 'opciones';
    case Number = 'numero';
    case Date = 'fecha';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto corto',
            self::Textarea => 'Texto largo',
            self::YesNo => 'Sí / No',
            self::Select => 'Una opción de una lista',
            self::Checkboxes => 'Varias opciones de una lista',
            self::Number => 'Número',
            self::Date => 'Fecha',
        };
    }

    public function color(): string
    {
        return 'gray';
    }

    /** El campo necesita una lista de opciones. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Checkboxes], true);
    }
}
