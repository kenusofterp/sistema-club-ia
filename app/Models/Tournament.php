<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Torneo con costo único y fecha máxima de pago. Puede durar uno o varios días; cada día tiene
 * sus niveles/actividades. Libre (is_open): se pueden anotar los alumnos de esos niveles.
 */
#[Fillable(['name', 'description', 'price', 'payment_due_date', 'is_open', 'instructor_id', 'cancelled_at', 'cancel_reason', 'created_by'])]
class Tournament extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'payment_due_date' => 'date',
            'is_open' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(TournamentDay::class)->orderBy('date');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    /** Ids de los niveles/actividades de todos los días. */
    public function activityIds(): array
    {
        return TournamentDay::query()
            ->where('tournament_id', $this->id)
            ->join('tournament_day_activity', 'tournament_day_activity.tournament_day_id', '=', 'tournament_days.id')
            ->distinct()
            ->pluck('tournament_day_activity.activity_id')
            ->all();
    }

    public function startsOn(): ?Carbon
    {
        return $this->days->first()?->date;
    }

    public function endsOn(): ?Carbon
    {
        return $this->days->last()?->date;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Los alumnos pueden anotarse (portal) hasta el día anterior al comienzo. */
    public function isSignupOpen(): bool
    {
        $start = $this->startsOn();

        return $this->is_open && ! $this->isCancelled() && $start !== null && today()->lt($start);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /** Torneos que todavía no terminaron. */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereHas('days', fn ($q) => $q->whereDate('date', '>=', today()));
    }
}
