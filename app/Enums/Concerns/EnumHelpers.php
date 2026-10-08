<?php

namespace App\Enums\Concerns;

trait EnumHelpers
{
    /** @return array<string, string> valor => etiqueta */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Clases Tailwind para el badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this->color()) {
            'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'yellow' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            'red' => 'bg-red-50 text-red-700 ring-red-600/20',
            'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
    }
}
