<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dinero devuelto a un socio por un cargo que no corresponde (ej. torneo cancelado). */
#[Fillable(['member_id', 'fee_id', 'amount', 'method', 'refund_date', 'notes', 'created_by'])]
class Refund extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'refund_date' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
