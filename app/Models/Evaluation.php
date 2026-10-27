<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pasada de una participante por un ejercicio, en un puesto (también es su lugar en la cola).
 * Se publica cuando los 3 jueces confirman: score = cálculo de la estrategia de la competencia.
 */
#[Fillable(['competition_id', 'competition_entry_id', 'competition_exercise_id', 'competition_station_id', 'position', 'status', 'score', 'published_at'])]
class Evaluation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'score' => 'decimal:3',
            'position' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'competition_entry_id');
    }

    public function competitionExercise(): BelongsTo
    {
        return $this->belongsTo(CompetitionExercise::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(CompetitionStation::class, 'competition_station_id');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(JudgeSheet::class)->orderBy('seat');
    }

    public function isPublished(): bool
    {
        return $this->status === EvaluationStatus::Published;
    }
}
