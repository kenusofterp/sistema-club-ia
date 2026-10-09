<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alumno anotado en un torneo. is_exception = no pertenece a los niveles del torneo
 * (lo sumó el profesor igual). source: profesor | portal.
 */
#[Fillable(['tournament_id', 'member_id', 'fee_id', 'source', 'is_exception', 'added_by'])]
class TournamentParticipant extends Model
{
    public const SOURCE_TEACHER = 'profesor';

    public const SOURCE_PORTAL = 'portal';

    protected function casts(): array
    {
        return ['is_exception' => 'boolean'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
