<?php

namespace App\Models;

use App\Enums\CriterionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Criterio congelado de un ejercicio en una competencia (copia del snapshot, no se edita). */
#[Fillable(['competition_exercise_id', 'source_criterion_id', 'name', 'type', 'min', 'max', 'decimals', 'weight', 'sort_order'])]
class CompetitionCriterion extends Model
{
    public $timestamps = false;

    protected $table = 'competition_criteria';

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

    public function competitionExercise(): BelongsTo
    {
        return $this->belongsTo(CompetitionExercise::class);
    }
}
