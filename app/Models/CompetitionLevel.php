<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nivel dentro de una competencia (los rankings son por nivel o, si tiene grupos, por grupo). */
#[Fillable(['competition_id', 'name', 'sort_order'])]
class CompetitionLevel extends Model
{
    public $timestamps = false;

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CompetitionGroup::class)->orderBy('sort_order')->orderBy('id');
    }
}
