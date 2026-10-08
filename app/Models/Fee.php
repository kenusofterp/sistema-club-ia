<?php

namespace App\Models;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Cargo en la cuenta corriente del socio (cuota social, actividad, reserva, etc.). */
#[Fillable(['member_id', 'type', 'activity_id', 'reservation_id', 'subscription_id', 'period', 'concept', 'amount', 'surcharge', 'paid_amount', 'due_date', 'status', 'cancel_reason', 'created_by'])]
class Fee extends Model
{
    use Auditable, BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => FeeType::class,
            'status' => FeeStatus::class,
            'period' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class)->withPivot('amount')->withTimestamps();
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', FeeStatus::open());
    }

    public function total(): string
    {
        return bcadd((string) $this->amount, (string) $this->surcharge, 2);
    }

    public function balance(): string
    {
        return bcsub($this->total(), (string) $this->paid_amount, 2);
    }

    public function isOpen(): bool
    {
        return in_array($this->status->value, FeeStatus::open(), true);
    }

    /** Recalcula el estado según lo pagado y la fecha de vencimiento. */
    public function resolveStatus(): FeeStatus
    {
        if ($this->status === FeeStatus::Cancelled) {
            return FeeStatus::Cancelled;
        }

        if (bccomp((string) $this->paid_amount, $this->total(), 2) >= 0) {
            return FeeStatus::Paid;
        }

        if ($this->due_date->lt(today())) {
            return FeeStatus::Overdue;
        }

        return bccomp((string) $this->paid_amount, '0', 2) > 0 ? FeeStatus::Partial : FeeStatus::Pending;
    }

    public function periodLabel(): ?string
    {
        return $this->period ? ucfirst($this->period->translatedFormat('F Y')) : null;
    }
}
