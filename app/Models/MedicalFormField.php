<?php

namespace App\Models;

use App\Enums\MedicalFieldType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Pregunta/campo del modelo de ficha médica de la entidad. */
#[Fillable(['section', 'label', 'type', 'options', 'help', 'is_required', 'is_active', 'sort_order'])]
class MedicalFormField extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'type' => MedicalFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** Respuesta lista para mostrar (null si está vacía). */
    public function display(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($this->type) {
            MedicalFieldType::YesNo => $value === 'si' ? 'Sí' : 'No',
            MedicalFieldType::Checkboxes => implode(', ', (array) $value),
            MedicalFieldType::Date => rescue(fn () => Carbon::parse($value)->format('d/m/Y'), (string) $value, false),
            default => (string) $value,
        };
    }
}
