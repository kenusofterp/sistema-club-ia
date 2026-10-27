<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Juez de un puesto (asiento 1 a 3). Un juez trabaja en un solo puesto por competencia. */
#[Fillable(['competition_id', 'competition_station_id', 'user_id', 'seat'])]
class StationJudge extends Model
{
    use Auditable;

    protected $table = 'competition_station_judges';

    protected function casts(): array
    {
        return ['seat' => 'integer'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(CompetitionStation::class, 'competition_station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
