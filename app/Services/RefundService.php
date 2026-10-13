<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Models\MemberCredit;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Devolución de dinero de un cargo que ya no corresponde (hoy: torneos cancelados). No toca el pago
 * original (que puede incluir otras cuotas o estar rendido): se registra aparte y descuenta lo pagado.
 */
class RefundService
{
    /** Cuánto se puede devolver de un cargo (0 si no corresponde devolver). */
    public function refundable(Fee $fee): string
    {
        // Lo que decide es que el torneo esté cancelado (el cargo puede haber quedado "pagado" si el
        // torneo se canceló antes de que la cancelación anulara también los cargos con pagos).
        $cancelledTournament = $fee->type === FeeType::Tournament
            && $fee->tournament?->isCancelled();

        return $cancelledTournament && bccomp((string) $fee->paid_amount, '0', 2) > 0
            ? (string) $fee->paid_amount
            : '0.00';
    }

    public function refund(Fee $fee, string $amount, PaymentMethod $method, User $by, ?Carbon $date = null, ?string $notes = null): Refund
    {
        $date ??= today();

        if ($date->isAfter(today())) {
            throw new BusinessRuleException('La fecha de la devolución no puede ser futura.');
        }

        return DB::transaction(function () use ($fee, $amount, $method, $by, $date, $notes) {
            $fee = $this->takeFromFee($fee, $amount);

            return Refund::create([
                'member_id' => $fee->member_id,
                'fee_id' => $fee->id,
                'amount' => $amount,
                'method' => $method,
                'refund_date' => $date->toDateString(),
                'notes' => $notes,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * En vez de devolver el dinero, queda como saldo a favor del socio para pagar otros cargos
     * (por ejemplo, la próxima cuota).
     */
    public function toCredit(Fee $fee, string $amount, User $by, ?string $notes = null): MemberCredit
    {
        return DB::transaction(function () use ($fee, $amount, $by, $notes) {
            $fee = $this->takeFromFee($fee, $amount);

            return MemberCredit::create([
                'member_id' => $fee->member_id,
                'amount' => $amount,
                'fee_id' => $fee->id,
                'description' => 'A cuenta: '.$fee->concept.($notes ? " · {$notes}" : ''),
                'created_by' => $by->id,
            ]);
        });
    }

    /** Descuenta del cargo el dinero que se devuelve o se deja a cuenta (dentro de una transacción). */
    private function takeFromFee(Fee $fee, string $amount): Fee
    {
        $fee = Fee::query()->lockForUpdate()->findOrFail($fee->id);
        $available = $this->refundable($fee);

        if (bccomp($available, '0', 2) <= 0) {
            throw new BusinessRuleException('Este cargo no tiene dinero para devolver.');
        }

        if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $available, 2) > 0) {
            throw new BusinessRuleException('El importe tiene que ser mayor a cero y no superar lo pagado ('.money($available).').');
        }

        // El torneo no se juega: el cargo queda anulado (no debe nada) con lo que todavía no se devolvió.
        $fee->update([
            'paid_amount' => bcsub((string) $fee->paid_amount, $amount, 2),
            'status' => FeeStatus::Cancelled,
            'cancel_reason' => $fee->cancel_reason ?: 'Torneo cancelado',
        ]);

        return $fee;
    }
}
