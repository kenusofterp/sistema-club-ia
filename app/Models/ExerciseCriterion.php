<?php

namespace App\Models;

use App\Enums\CriterionType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Criterio de la plantilla de un ejercicio. Al iniciar una competencia se copia (snapshot) y deja de afectarla. */
#[Fillable(['exercise_id', 'name', 'type', 'min', 'max', 'decimals', 'weight', 'sort_order'])]
class ExerciseCriterion extends Model
{
    use Auditable;

    protected $table = 'exercise_criteria';

    protected function casts(): array
    {
        return [
            'type' => CriterionType::class,
            'min' => 'decimal:3',
            'max' => 'decimal:3',
            'decimals' => 'integer',
            'weight' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
