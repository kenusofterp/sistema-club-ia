<?php

namespace App\Models;

use App\Enums\ScoreChangeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de modificación de una nota confirmada. Guarda valores y totales anteriores y nuevos, quién lo
 * pidió y quién lo resolvió: es el registro de la modificación (la nota nunca cambia sin uno aprobado).
 */
#[Fillable([
    'competition_id', 'evaluation_id', 'judge_sheet_id', 'requested_by', 'reason', 'old_values', 'new_values',
    'old_total', 'new_total', 'old_score', 'new_score', 'status', 'resolved_by', 'resolved_at', 'resolution_note',
])]
class ScoreChangeRequest extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'old_total' => 'decimal:3',
            'new_total' => 'decimal:3',
            'old_score' => 'decimal:3',
            'new_score' => 'decimal:3',
            'status' => ScoreChangeStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(JudgeSheet::class, 'judge_sheet_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === ScoreChangeStatus::Pending;
    }
}
