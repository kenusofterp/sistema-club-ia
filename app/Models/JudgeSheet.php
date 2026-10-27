<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Planilla de un juez en una evaluación: sus valores por criterio y su total. Confirmada, solo cambia con autorización. */
#[Fillable(['evaluation_id', 'user_id', 'seat', 'total', 'confirmed_at'])]
class JudgeSheet extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['seat' => 'integer', 'total' => 'decimal:3', 'confirmed_at' => 'datetime'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(JudgeScore::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /** @return array<int, string> criterio => valor */
    public function values(): array
    {
        return $this->scores->mapWithKeys(fn (JudgeScore $s) => [$s->competition_criterion_id => (string) $s->value])->all();
    }
}
