<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Grupo dentro de un nivel de la competencia. */
#[Fillable(['competition_level_id', 'name', 'sort_order'])]
class CompetitionGroup extends Model
{
    public $timestamps = false;

    public function level(): BelongsTo
    {
        return $this->belongsTo(CompetitionLevel::class, 'competition_level_id');
    }
}
