<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rendición del efectivo que un profesor cobró a los socios y entrega a la entidad. */
#[Fillable(['user_id', 'amount', 'payments_count', 'status', 'notes', 'confirmed_by', 'confirmed_at'])]
class CashSettlement extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'amount' => 'decimal:2',
            'payments_count' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'settlement_id');
    }
}
