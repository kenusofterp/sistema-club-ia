<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Puesto de evaluación: un ejercicio, opcionalmente limitado a un nivel/grupo, con exactamente 3 jueces. */
#[Fillable(['competition_id', 'competition_exercise_id', 'competition_level_id', 'competition_group_id', 'name', 'sort_order'])]
class CompetitionStation extends Model
{
    use Auditable;

    public const JUDGES = 3;

    protected static function booted(): void
    {
        static::creating(function (CompetitionStation $station) {
            $station->display_token ??= Str::random(40);
        });
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionExercise(): BelongsTo
    {
        return $this->belongsTo(CompetitionExercise::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CompetitionLevel::class, 'competition_level_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetitionGroup::class, 'competition_group_id');
    }

    public function judges(): HasMany
    {
        return $this->hasMany(StationJudge::class)->orderBy('seat');
    }

    /** Cola de pasada (orden editable). */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class)->orderBy('position')->orderBy('id');
    }

    /** Participante actual: la primera pendiente de la cola. */
    public function currentEvaluation(): ?Evaluation
    {
        return $this->evaluations()->where('status', EvaluationStatus::Pending)->first();
    }

    public function hasFullPanel(): bool
    {
        return ($this->judges_count ?? $this->judges()->count()) === self::JUDGES;
    }
}
