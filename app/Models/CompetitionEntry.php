<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inscripción de una participante en una competencia: nivel, grupo y dorsal. */
#[Fillable(['competition_id', 'competitor_id', 'competition_level_id', 'competition_group_id', 'bib_number'])]
class CompetitionEntry extends Model
{
    use Auditable;

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class)->withTrashed();
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CompetitionLevel::class, 'competition_level_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetitionGroup::class, 'competition_group_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** «Nivel 3 · Grupo A» */
    public function categoryLabel(): string
    {
        return collect([$this->level?->name, $this->group?->name])->filter()->implode(' · ');
    }
}
