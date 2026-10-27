<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Valor que puso un juez en un criterio (sí/no se guarda como 1/0). */
#[Fillable(['judge_sheet_id', 'competition_criterion_id', 'value'])]
class JudgeScore extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['value' => 'decimal:3'];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(JudgeSheet::class, 'judge_sheet_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(CompetitionCriterion::class, 'competition_criterion_id');
    }
}
