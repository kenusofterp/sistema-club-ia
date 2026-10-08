<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeeService
{
    /**
     * Genera los cargos mensuales de un socio (cuota social + actividades).
     * Es idempotente: si el cargo del período ya existe, no se duplica.
     *
     * @return int cantidad de cargos creados
     */
    public function generateForMember(Member $member, Carbon $period): int
    {
        $period = $period->copy()->startOfMonth();

        if (! $member->isActive()) {
            return 0;
        }

        // No se cobran meses anteriores al ingreso del socio.
        if ($member->admission_date && $member->admission_date->copy()->startOfMonth()->gt($period)) {
            return 0;
        }

        $created = 0;
        $category = $member->category;

        if (bccomp((string) $category->monthly_fee, '0', 2) > 0) {
            $created += (int) $this->createPeriodFee(
                $member,
                FeeType::Membership,
                $period,
                'Cuota social '.$category->name.' - '.ucfirst($period->translatedFormat('F Y')),
                (string) $category->monthly_fee,
            );
        }

        $enrollments = $member->enrollments()
            ->with('activity')
            ->where('status', EnrollmentStatus::Active)
            ->whereDate('start_date', '<=', $period->copy()->endOfMonth())
            ->get();

        foreach ($enrollments as $enrollment) {
            $created += (int) $this->generateActivityFee($member, $enrollment->activity, $period);
        }

        return $created;
    }

    public function generateActivityFee(Member $member, Activity $activity, Carbon $period): bool
    {
        if (bccomp((string) $activity->monthly_fee, '0', 2) <= 0) {
            return false;
        }

        return $this->createPeriodFee(
            $member,
            FeeType::Activity,
            $period->copy()->startOfMonth(),
            $activity->name.' - '.ucfirst($period->translatedFormat('F Y')),
            (string) $activity->monthly_fee,
            $activity->id,
        );
    }

    public function createCharge(Member $member, FeeType $type, string $concept, string $amount, Carbon $dueDate, ?int $reservationId = null): Fee
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new BusinessRuleException('El importe del cargo debe ser mayor a cero.');
        }

        return Fee::create([
            'member_id' => $member->id,
            'type' => $type,
            'reservation_id' => $reservationId,
            'concept' => $concept,
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => FeeStatus::Pending,
            'created_by' => auth()->id(),
        ]);
    }

    /** Solo se anulan cargos sin pagos imputados; si tiene pagos, primero hay que anular el pago. */
    public function cancel(Fee $fee, string $reason): void
    {
        if ($fee->status === FeeStatus::Cancelled) {
            throw new BusinessRuleException('El cargo ya está anulado.');
        }

        if (bccomp((string) $fee->paid_amount, '0', 2) > 0) {
            throw new BusinessRuleException('No se puede anular un cargo con pagos imputados. Anule primero el pago.');
        }

        $fee->update(['status' => FeeStatus::Cancelled, 'cancel_reason' => $reason]);
    }

    /**
     * Marca como vencidos los cargos impagos con vencimiento anterior a hoy
     * y aplica el recargo por mora configurado (una sola vez por cargo).
     *
     * @return int cantidad de cargos vencidos
     */
    public function markOverdue(): int
    {
        $percent = (string) (setting('club.surcharge_percent', 0) ?: 0);
        $count = 0;

        Fee::query()
            ->whereIn('status', [FeeStatus::Pending->value, FeeStatus::Partial->value])
            ->whereDate('due_date', '<', today())
            ->chunkById(500, function ($fees) use ($percent, &$count) {
                foreach ($fees as $fee) {
                    $surcharge = (string) $fee->surcharge;
                    if (bccomp($percent, '0', 2) > 0 && bccomp($surcharge, '0', 2) === 0) {
                        $surcharge = bcdiv(bcmul((string) $fee->amount, $percent, 4), '100', 2);
                    }

                    $fee->update(['status' => FeeStatus::Overdue, 'surcharge' => $surcharge]);
                    $count++;
                }
            });

        return $count;
    }

    public function dueDateFor(Carbon $period): Carbon
    {
        $day = min((int) setting('club.fee_due_day', 10), $period->daysInMonth);

        return $period->copy()->startOfMonth()->setDay($day);
    }

    private function createPeriodFee(Member $member, FeeType $type, Carbon $period, string $concept, string $amount, ?int $activityId = null): bool
    {
        $exists = Fee::query()
            ->where('member_id', $member->id)
            ->where('type', $type)
            ->whereDate('period', $period)
            ->where('activity_id', $activityId)
            ->where('status', '!=', FeeStatus::Cancelled)
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            // Savepoint: si otro proceso creó el mismo cargo en paralelo, el índice único lo impide.
            DB::transaction(fn () => Fee::create([
                'member_id' => $member->id,
                'type' => $type,
                'activity_id' => $activityId,
                'period' => $period,
                'concept' => $concept,
                'amount' => $amount,
                'due_date' => $this->dueDateFor($period),
                'status' => FeeStatus::Pending,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
