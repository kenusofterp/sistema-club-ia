<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashSettlement;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\SettlementSubmittedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Efectivo que cobran los profesores a los alumnos de sus actividades. La plata es de la entidad: si la
 * configuración lo exige, el profesor la rinde y coordinación o tesorería confirma al recibirla.
 * Con "payments.cash_requires_settlement" desactivado (profesor dueño) no hay rendición.
 */
class CashCollectionService
{
    public function __construct(private PaymentService $payments, private LevelLessonService $levels, private TournamentService $tournaments) {}

    /** ¿El socio es alumno del profesor (inscripto en sus actividades o anotado en sus torneos)? */
    public function isStudentOf(User $teacher, Member $member): bool
    {
        return $this->studentsQuery($teacher)->whereKey($member->id)->exists();
    }

    /**
     * Alumnos del profesor, para buscar a quién cobrar: inscriptos en sus actividades y participantes
     * de sus torneos (incluye las excepciones que no están en sus niveles).
     */
    public function studentsQuery(User $teacher): Builder
    {
        $activityIds = $this->levels->activityIdsTaughtBy($teacher);
        $tournamentIds = $this->tournaments->queryFor($teacher)->active()->pluck('id');

        return Member::query()->where(fn ($q) => $q
            ->whereHas('enrollments', fn ($e) => $e
                ->where('status', EnrollmentStatus::Active)
                ->whereIn('activity_id', $activityIds))
            ->orWhereHas('tournamentParticipations', fn ($t) => $t->whereIn('tournament_id', $tournamentIds)));
    }

    /**
     * Registra un cobro en efectivo hecho por el profesor (cuotas de la entidad, no cargos propios del profesor).
     *
     * @param  array<int>  $feeIds
     */
    public function collect(User $teacher, Member $member, array $feeIds, string $amount, ?string $notes = null): Payment
    {
        if (! setting('payments.instructors_collect_cash', true)) {
            throw new BusinessRuleException('En esta entidad los profesores no cobran en efectivo.');
        }

        if (! $this->isStudentOf($teacher, $member)) {
            throw new BusinessRuleException("{$member->fullName()} no es alumno/a de tus grupos.");
        }

        if (Fee::whereIn('id', $feeIds)->whereNotNull('instructor_id')->exists()) {
            throw new BusinessRuleException('Esos cargos son de un profesor: se cobran desde "Mis cobros".');
        }

        return $this->payments->register($member, $amount, PaymentMethod::Cash, $feeIds, today(), null, $notes ?: 'Cobrado por '.$teacher->name, $teacher);
    }

    public function requiresSettlement(): bool
    {
        return (bool) setting('payments.cash_requires_settlement', true);
    }

    /** Cobros en efectivo que el profesor tiene en su poder (sin rendir). */
    public function pendingQuery(User $teacher): Builder
    {
        return Payment::query()
            ->where('received_by', $teacher->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Confirmed)
            ->whereNull('settlement_id')
            ->whereNull('instructor_id');
    }

    public function pendingTotal(User $teacher): string
    {
        return (string) ($this->pendingQuery($teacher)->sum('amount') ?: '0');
    }

    /** El profesor rinde todo el efectivo pendiente; queda por confirmar. */
    public function settle(User $teacher, ?string $notes = null): CashSettlement
    {
        if (! $this->requiresSettlement()) {
            throw new BusinessRuleException('En esta entidad el efectivo no se rinde.');
        }

        $settlement = DB::transaction(function () use ($teacher, $notes) {
            $payments = $this->pendingQuery($teacher)->lockForUpdate()->get();
            if ($payments->isEmpty()) {
                throw new BusinessRuleException('No tenés efectivo pendiente de rendir.');
            }

            $settlement = CashSettlement::create([
                'user_id' => $teacher->id,
                'amount' => $payments->reduce(fn (string $carry, Payment $p) => bcadd($carry, (string) $p->amount, 2), '0'),
                'payments_count' => $payments->count(),
                'status' => SettlementStatus::Pending,
                'notes' => $notes ? mb_substr($notes, 0, 500) : null,
            ]);

            Payment::whereIn('id', $payments->pluck('id'))->update(['settlement_id' => $settlement->id]);

            return $settlement;
        });

        $reviewers = User::withPermissionIn('rendiciones.gestionar', $settlement->organization_id)->reject(fn (User $u) => $u->is($teacher));
        Notification::send($reviewers, new SettlementSubmittedNotification($settlement));

        return $settlement;
    }

    /** Quien rinde puede confirmar su propia rendición (profesor que trabaja solo, a modo de control). */
    public function selfConfirmAllowed(): bool
    {
        return (bool) setting('payments.self_settlement_allowed', false);
    }

    /** Coordinación o tesorería confirma que recibió el dinero (o el mismo profesor, si está habilitado). */
    public function confirm(CashSettlement $settlement, User $by): CashSettlement
    {
        if ($settlement->status !== SettlementStatus::Pending) {
            throw new BusinessRuleException('La rendición ya fue confirmada.');
        }

        if ($settlement->user_id === $by->id && ! $by->isSuperAdmin() && ! $this->selfConfirmAllowed()) {
            throw new BusinessRuleException('Otra persona tiene que confirmar tu rendición.');
        }

        $settlement->update([
            'status' => SettlementStatus::Confirmed,
            'confirmed_by' => $by->id,
            'confirmed_at' => now(),
        ]);

        return $settlement;
    }

    /** Devuelve una rendición sin confirmar: los cobros vuelven a quedar pendientes del profesor. */
    public function revert(CashSettlement $settlement): void
    {
        if ($settlement->status !== SettlementStatus::Pending) {
            throw new BusinessRuleException('Una rendición confirmada no se puede deshacer.');
        }

        DB::transaction(function () use ($settlement) {
            $settlement->payments()->update(['settlement_id' => null]);
            $settlement->delete();
        });
    }
}
