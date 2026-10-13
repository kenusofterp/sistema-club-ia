<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento del saldo a favor: + dinero que queda a cuenta, − saldo usado para pagar. */
#[Fillable(['member_id', 'amount', 'fee_id', 'payment_id', 'description', 'created_by'])]
class MemberCredit extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
