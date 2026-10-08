<?php

namespace App\Models;

use App\Enums\AccessResult;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** Contratación de un plan por un socio, por un período determinado. */
#[Fillable(['member_id', 'plan_id', 'start_date', 'end_date', 'price', 'status', 'auto_renew', 'renewed_from_id', 'activated_at', 'cancelled_at', 'cancel_reason', 'created_by'])]
class Subscription extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'price' => 'decimal:2',
            'auto_renew' => 'boolean',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'renewed_from_id');
    }

    public function renewal(): HasOne
    {
        return $this->hasOne(Subscription::class, 'renewed_from_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    /** Suscripciones activas y vigentes en una fecha. */
    public function scopeCurrent(Builder $query, ?Carbon $date = null): void
    {
        $date ??= today();
        $query->where('status', SubscriptionStatus::Active)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    public function isCurrent(?Carbon $date = null): bool
    {
        $date ??= today();

        return $this->status === SubscriptionStatus::Active
            && $this->start_date->lte($date)
            && $this->end_date->gte($date);
    }

    public function daysLeft(): int
    {
        return max(0, (int) today()->diffInDays($this->end_date, false) + 1);
    }

    /** Inicio del período en que se cuentan las visitas (semana, mes calendario o todo el plan). */
    public function visitPeriodStart(Carbon $at): Carbon
    {
        $start = match ($this->plan->visit_period) {
            'semana' => $at->copy()->startOfWeek(),
            'mes' => $at->copy()->startOfMonth(),
            default => $this->start_date->copy(),
        };

        return $start->max($this->start_date);
    }

    /** Visitas usadas: días distintos con ingreso permitido en el período. */
    public function visitsUsed(?Carbon $at = null): int
    {
        $at ??= now();

        return (int) $this->accessLogs()
            ->where('result', AccessResult::Granted)
            ->where('checked_at', '>=', $this->visitPeriodStart($at)->startOfDay())
            ->where('checked_at', '<=', $at)
            ->selectRaw('COUNT(DISTINCT DATE(checked_at)) as n')
            ->value('n');
    }

    public function visitsRemaining(?Carbon $at = null): ?int
    {
        if (! $this->plan->visit_limit) {
            return null;
        }

        return max(0, $this->plan->visit_limit - $this->visitsUsed($at));
    }

    public function periodLabel(): string
    {
        return $this->start_date->format('d/m/Y').' al '.$this->end_date->format('d/m/Y');
    }
}
