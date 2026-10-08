<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * Registra un pago y lo imputa a los cargos indicados, del vencimiento más antiguo al más nuevo.
     *
     * @param  array<int>  $feeIds
     */
    public function register(
        Member $member,
        string $amount,
        PaymentMethod $method,
        array $feeIds,
        ?Carbon $date = null,
        ?string $reference = null,
        ?string $notes = null,
        ?User $receivedBy = null,
    ): Payment {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new BusinessRuleException('El importe del pago debe ser mayor a cero.');
        }

        if ($feeIds === []) {
            throw new BusinessRuleException('Seleccione al menos un cargo a cancelar.');
        }

        $date ??= today();
        if ($date->isFuture()) {
            throw new BusinessRuleException('La fecha de pago no puede ser futura.');
        }

        $payment = DB::transaction(function () use ($member, $amount, $method, $feeIds, $date, $reference, $notes, $receivedBy) {
            $fees = Fee::query()
                ->whereIn('id', $feeIds)
                ->where('member_id', $member->id)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($fees->count() !== count(array_unique($feeIds))) {
                throw new BusinessRuleException('Alguno de los cargos seleccionados no pertenece al socio.');
            }

            if ($fees->contains(fn (Fee $fee) => ! $fee->isOpen())) {
                throw new BusinessRuleException('Alguno de los cargos seleccionados ya está pagado o anulado.');
            }

            // Cobros separados: un pago es del club o de un único profesor.
            if ($fees->pluck('instructor_id')->unique()->count() > 1) {
                throw new BusinessRuleException('No se pueden cobrar en un mismo pago cargos del club y de un profesor, ni de profesores distintos. Registrá un pago para cada uno.');
            }

            $totalBalance = $fees->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
            if (bccomp($amount, $totalBalance, 2) > 0) {
                throw new BusinessRuleException('El importe ('.money($amount).') supera el saldo de los cargos seleccionados ('.money($totalBalance).').');
            }

            $payment = Payment::create([
                'receipt_number' => $this->nextReceiptNumber($member->organization_id),
                'member_id' => $member->id,
                'instructor_id' => $fees->first()->instructor_id,
                'amount' => $amount,
                'payment_date' => $date,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'status' => PaymentStatus::Confirmed,
                'received_by' => $receivedBy?->id,
            ]);

            $remaining = $amount;
            foreach ($fees as $fee) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $applied = bccomp($remaining, $fee->balance(), 2) >= 0 ? $fee->balance() : $remaining;
                $payment->fees()->attach($fee->id, ['amount' => $applied]);

                $fee->paid_amount = bcadd((string) $fee->paid_amount, $applied, 2);
                $fee->status = $fee->resolveStatus();
                $fee->save();
                $this->subscriptions->handleFeePaid($fee);

                $remaining = bcsub($remaining, $applied, 2);
            }

            return $payment;
        });

        if ($member->email) {
            $member->notify(new PaymentReceivedNotification($payment));
        }

        return $payment;
    }

    /**
     * Número de recibo correlativo por entidad (R-00000001, R-00000002…).
     * Debe llamarse dentro de una transacción: el lock se libera al confirmarla.
     */
    private function nextReceiptNumber(int $organizationId): string
    {
        DB::select('SELECT pg_advisory_xact_lock(?, ?)', [hexdec(substr(md5('receipt_number'), 0, 7)), $organizationId]);

        $last = (int) Payment::acrossOrganizations()
            ->where('organization_id', $organizationId)
            ->selectRaw("MAX(CAST(NULLIF(regexp_replace(receipt_number, '\\D', '', 'g'), '') AS BIGINT)) as n")
            ->value('n');

        return sprintf('R-%08d', $last + 1);
    }

    /** Anula un pago y revierte lo imputado en cada cargo. */
    public function cancel(Payment $payment, string $reason, ?User $by = null): void
    {
        if ($payment->isCancelled()) {
            throw new BusinessRuleException('El pago ya está anulado.');
        }

        DB::transaction(function () use ($payment, $reason, $by) {
            $fees = $payment->fees()->lockForUpdate()->get();

            foreach ($fees as $fee) {
                $fee->paid_amount = bcsub((string) $fee->paid_amount, (string) $fee->pivot->amount, 2);
                if ($fee->status !== FeeStatus::Cancelled) {
                    $fee->status = FeeStatus::Pending;
                    $fee->status = $fee->resolveStatus();
                }
                $fee->save();
                $this->subscriptions->handleFeeReverted($fee);
            }

            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $by?->id,
                'cancel_reason' => $reason,
            ]);
        });
    }
}
