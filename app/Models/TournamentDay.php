<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Un día del torneo con los niveles/actividades que participan ese día. */
#[Fillable(['tournament_id', 'date', 'notes'])]
class TournamentDay extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'tournament_day_activity')->withTrashed()->orderBy('name');
    }
}
