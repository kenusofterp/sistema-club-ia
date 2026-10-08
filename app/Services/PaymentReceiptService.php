<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Models\Member;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Notifications\ReceiptReviewedNotification;
use App\Notifications\ReceiptSubmittedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Pagos por transferencia informados por el socio con el comprobante. Según la configuración se acreditan
 * al instante (y tesorería puede anular el pago) o quedan en revisión hasta que alguien los aprueba.
 */
class PaymentReceiptService
{
    public function __construct(private PaymentService $payments) {}

    /** @param  array<int>  $feeIds */
    public function submit(Member $member, array $feeIds, string $amount, Carbon $transferDate, string $filePath, ?string $reference = null): PaymentReceipt
    {
        if (! setting('payments.receipts_enabled', true)) {
            throw new BusinessRuleException('La entidad no recibe comprobantes de transferencia por la app.');
        }

        if ($feeIds === []) {
            throw new BusinessRuleException('Elegí qué cuotas estás pagando.');
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new BusinessRuleException('El importe debe ser mayor a cero.');
        }

        if ($transferDate->isFuture()) {
            throw new BusinessRuleException('La fecha de la transferencia no puede ser futura.');
        }

        $receipt = DB::transaction(function () use ($member, $feeIds, $amount, $transferDate, $filePath, $reference) {
            $fees = Fee::whereIn('id', $feeIds)->where('member_id', $member->id)->lockForUpdate()->get();

            if ($fees->count() !== count(array_unique($feeIds))) {
                throw new BusinessRuleException('Alguna de las cuotas elegidas no es tuya.');
            }

            if ($fees->contains(fn (Fee $fee) => ! $fee->isOpen())) {
                throw new BusinessRuleException('Alguna de las cuotas elegidas ya está paga o anulada.');
            }

            $inReview = PaymentReceipt::where('member_id', $member->id)
                ->where('status', ReceiptStatus::Pending)
                ->whereHas('fees', fn ($q) => $q->whereIn('fees.id', $feeIds))
                ->exists();
            if ($inReview) {
                throw new BusinessRuleException('Ya informaste un pago de alguna de esas cuotas y está en revisión.');
            }

            $balance = $fees->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
            if (bccomp($amount, $balance, 2) > 0) {
                throw new BusinessRuleException('El importe ('.money($amount).') supera lo que debés de esas cuotas ('.money($balance).').');
            }

            $receipt = PaymentReceipt::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'transfer_date' => $transferDate->toDateString(),
                'reference' => $reference,
                'file_path' => $filePath,
                'status' => ReceiptStatus::Pending,
            ]);
            $receipt->fees()->attach($fees->pluck('id'));

            return $receipt;
        });

        if (setting('payments.receipts_auto_approve', false)) {
            return $this->approve($receipt);
        }

        Notification::send(User::withPermissionIn('comprobantes.revisar', $receipt->organization_id), new ReceiptSubmittedNotification($receipt));

        return $receipt;
    }

    /** Acredita el comprobante: registra el pago por transferencia imputado a las cuotas informadas. */
    public function approve(PaymentReceipt $receipt, ?User $by = null): PaymentReceipt
    {
        if ($receipt->status !== ReceiptStatus::Pending) {
            throw new BusinessRuleException('El comprobante ya fue revisado.');
        }

        DB::transaction(function () use ($receipt, $by) {
            $feeIds = $receipt->fees()->whereIn('status', FeeStatus::open())->pluck('fees.id')->all();
            if ($feeIds === []) {
                throw new BusinessRuleException('Las cuotas de este comprobante ya están pagas. Rechazalo indicando el motivo.');
            }

            $payment = $this->payments->register(
                $receipt->member,
                (string) $receipt->amount,
                PaymentMethod::Transfer,
                $feeIds,
                $receipt->transfer_date->copy(),
                $receipt->reference,
                "Comprobante #{$receipt->id} informado por el socio",
                $by,
            );

            $receipt->update([
                'status' => ReceiptStatus::Approved,
                'payment_id' => $payment->id,
                'reviewed_by' => $by?->id,
                'reviewed_at' => now(),
            ]);
        });

        $receipt->member->notify(new ReceiptReviewedNotification($receipt));

        return $receipt;
    }

    public function reject(PaymentReceipt $receipt, string $reason, User $by): PaymentReceipt
    {
        if ($receipt->status !== ReceiptStatus::Pending) {
            throw new BusinessRuleException('El comprobante ya fue revisado.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new BusinessRuleException('Indicá el motivo del rechazo: se le informa al socio.');
        }

        $receipt->update([
            'status' => ReceiptStatus::Rejected,
            'reject_reason' => mb_substr($reason, 0, 250),
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ]);

        $receipt->member->notify(new ReceiptReviewedNotification($receipt));

        return $receipt;
    }
}
