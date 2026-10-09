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
        // Cuota individual de la inscripción (descuento fijo); si no tiene, la de la actividad.
        // Sobre esa base se aplica la beca en porcentaje, si la hay.
        $enrollment = $member->enrollments()
            ->where('activity_id', $activity->id)
            ->where('status', EnrollmentStatus::Active)
            ->first(['fee_amount', 'scholarship_percent']);
        $amount = (string) ($enrollment?->fee_amount ?? $activity->monthly_fee);
        $percent = (string) ($enrollment?->scholarship_percent ?? '0');
        $concept = $activity->name.' - '.ucfirst($period->translatedFormat('F Y'));

        if (bccomp($percent, '0', 2) > 0) {
            $amount = bcdiv(bcmul($amount, bcsub('100', $percent, 2), 4), '100', 2);
            $concept .= ' (beca '.rtrim(rtrim(number_format((float) $percent, 2, ',', ''), '0'), ',').' %)';
        }

        if (bccomp($amount, '0', 2) <= 0) {
            return false;
        }

        return $this->createPeriodFee(
            $member,
            FeeType::Activity,
            $period->copy()->startOfMonth(),
            $concept,
            $amount,
            $activity->id,
        );
    }

    /**
     * Inscripción anual a una actividad/nivel: un cargo por socio, actividad y año (idempotente).
     */
    public function generateRegistrationFee(Member $member, Activity $activity, int $year): bool
    {
        $amount = (string) $activity->enrollment_fee;

        if (bccomp($amount, '0', 2) <= 0) {
            return false;
        }

        $period = Carbon::create($year, 1, 1);
        // Vence en el próximo vencimiento de cuotas que todavía no pasó.
        $dueDate = $this->dueDateFor($year === today()->year ? today() : $period);
        if ($dueDate->lt(today())) {
            $dueDate = $this->dueDateFor(today()->addMonthNoOverflow());
        }

        return $this->createPeriodFee(
            $member,
            FeeType::Registration,
            $period,
            "Inscripción {$year} - {$activity->name}",
            $amount,
            $activity->id,
            $dueDate,
        );
    }

    public function createCharge(
        Member $member,
        FeeType $type,
        string $concept,
        string $amount,
        Carbon $dueDate,
        ?int $reservationId = null,
        ?int $instructorId = null,
        ?int $lessonId = null,
        ?int $tournamentId = null,
    ): Fee {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new BusinessRuleException('El importe del cargo debe ser mayor a cero.');
        }

        return Fee::create([
            'member_id' => $member->id,
            'type' => $type,
            'reservation_id' => $reservationId,
            'instructor_id' => $instructorId,
            'lesson_id' => $lessonId,
            'tournament_id' => $tournamentId,
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
                    // Los torneos vencen pero no llevan recargo por mora.
                    if ($fee->type !== FeeType::Tournament && bccomp($percent, '0', 2) > 0 && bccomp($surcharge, '0', 2) === 0) {
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

    private function createPeriodFee(Member $member, FeeType $type, Carbon $period, string $concept, string $amount, ?int $activityId = null, ?Carbon $dueDate = null): bool
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
                'due_date' => $dueDate ?? $this->dueDateFor($period),
                'status' => FeeStatus::Pending,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
