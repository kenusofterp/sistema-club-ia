<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ejercicio incluido en una competencia. Al iniciarla se guarda el snapshot de la plantilla (JSONB) y su
 * proyección en competition_criteria: los cambios posteriores del ejercicio no alteran las notas.
 */
#[Fillable(['competition_id', 'exercise_id', 'sort_order', 'template_snapshot', 'snapshotted_at'])]
class CompetitionExercise extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['template_snapshot' => 'array', 'snapshotted_at' => 'datetime', 'sort_order' => 'integer'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class)->withTrashed();
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(CompetitionCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(CompetitionStation::class);
    }

    /** Nombre congelado en el snapshot (o el actual si todavía no se inició). */
    public function name(): string
    {
        return $this->template_snapshot['exercise']['name'] ?? $this->exercise?->name ?? '';
    }

    public function isSnapshotted(): bool
    {
        return $this->snapshotted_at !== null;
    }
}
