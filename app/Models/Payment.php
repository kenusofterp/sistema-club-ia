<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['receipt_number', 'member_id', 'instructor_id', 'amount', 'payment_date', 'method', 'reference', 'notes', 'status', 'received_by', 'settlement_id', 'cancelled_at', 'cancelled_by', 'cancel_reason'])]
class Payment extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashSettlement::class, 'settlement_id');
    }

    /** Profesor que cobró (null = cobro de la entidad). */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function fees(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class)->withPivot('amount')->withTimestamps();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', PaymentStatus::Confirmed);
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::Cancelled;
    }
}
