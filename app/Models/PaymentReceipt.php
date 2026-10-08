<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Comprobante de transferencia que sube el socio para pagar cuotas. */
#[Fillable(['member_id', 'amount', 'transfer_date', 'reference', 'file_path', 'status', 'payment_id', 'reviewed_by', 'reviewed_at', 'reject_reason'])]
class PaymentReceipt extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function fees(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function fileUrl(): ?string
    {
        return storage_url($this->file_path);
    }

    public function isPdf(): bool
    {
        return str_ends_with(strtolower($this->file_path), '.pdf');
    }
}
