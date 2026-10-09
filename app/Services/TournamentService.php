<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Torneos. El cargo de cada participante es un cargo de la entidad (sin instructor_id), así se cobra
 * igual que las cuotas: efectivo del profesor, pago en secretaría o comprobante de transferencia,
 * y se puede pagar junto con la cuota o por separado.
 */
class TournamentService
{
    public function __construct(private FeeService $fees, private LevelLessonService $levels) {}

    /**
     * @param  array{name: string, description?: ?string, price: string, payment_due_date: string, is_open: bool}  $data
     * @param  array<int, array{date: string, notes?: ?string, activity_ids: array<int, int>}>  $days
     */
    public function save(?Tournament $tournament, array $data, array $days, User $by): Tournament
    {
        if ($days === []) {
            throw new BusinessRuleException('El torneo tiene que tener al menos un día.');
        }

        $dates = array_map(fn ($d) => Carbon::parse($d['date'])->toDateString(), $days);
        if (count($dates) !== count(array_unique($dates))) {
            throw new BusinessRuleException('Hay días repetidos. Si un día juegan varios '.mb_strtolower(activity_label(true)).', elegilos en el mismo día.');
        }

        foreach ($days as $day) {
            if (empty($day['activity_ids'])) {
                throw new BusinessRuleException('Cada día del torneo tiene que tener al menos un '.mb_strtolower(activity_label()).'.');
            }
        }

        if (bccomp($data['price'], '0', 2) < 0) {
            throw new BusinessRuleException('El costo no puede ser negativo.');
        }

        return DB::transaction(function () use ($tournament, $data, $days, $by) {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'payment_due_date' => $data['payment_due_date'],
                'is_open' => (bool) $data['is_open'],
            ];

            if ($tournament) {
                $tournament->update($attributes);
                $tournament->days()->delete();
            } else {
                $tournament = Tournament::create([...$attributes, 'instructor_id' => $by->id, 'created_by' => $by->id]);
            }

            foreach ($days as $day) {
                $record = TournamentDay::create([
                    'tournament_id' => $tournament->id,
                    'date' => $day['date'],
                    'notes' => $day['notes'] ?? null,
                ]);
                $record->activities()->sync(array_map('intval', $day['activity_ids']));
            }

            $this->syncUnpaidFees($tournament);

            return $tournament->load('days.activities');
        });
    }

    /** Si cambió el costo, el nombre o la fecha máxima, se actualizan los cargos que todavía no tienen pagos. */
    private function syncUnpaidFees(Tournament $tournament): void
    {
        $tournament->fees()
            ->whereIn('status', FeeStatus::open())
            ->where('paid_amount', 0)
            ->get()
            ->each(function (Fee $fee) use ($tournament) {
                $fee->fill([
                    'amount' => $tournament->price,
                    'concept' => $this->concept($tournament),
                    'due_date' => $this->dueDate($tournament),
                ]);
                $fee->status = $fee->resolveStatus();
                $fee->save();
            });
    }

    /** Torneos que ve un usuario: todos con agenda.todas; si no, los suyos y los de sus niveles. */
    public function queryFor(User $user): Builder
    {
        if ($user->can('agenda.todas')) {
            return Tournament::query();
        }

        $mine = $this->levels->activityIdsTaughtBy($user);

        return Tournament::query()->where(fn ($q) => $q
            ->where('instructor_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->orWhereHas('days.activities', fn ($a) => $a->whereIn('activities.id', $mine)));
    }

    /** Alumnos que pueden participar: inscriptos activos en algún nivel del torneo. */
    public function eligibleMembersQuery(Tournament $tournament): Builder
    {
        $activityIds = $tournament->activityIds();

        return Member::active()->whereHas('enrollments', fn ($q) => $q
            ->where('status', EnrollmentStatus::Active)
            ->whereIn('activity_id', $activityIds));
    }

    public function isEligible(Tournament $tournament, Member $member): bool
    {
        return $this->eligibleMembersQuery($tournament)->whereKey($member->id)->exists();
    }

    /**
     * Suma un alumno. Si no es de los niveles del torneo queda como excepción
     * (el profesor lo agregó igual, por ejemplo a último momento).
     */
    public function addParticipant(Tournament $tournament, Member $member, ?User $by, string $source = TournamentParticipant::SOURCE_TEACHER): TournamentParticipant
    {
        if ($tournament->isCancelled()) {
            throw new BusinessRuleException('El torneo está cancelado.');
        }

        if (! $member->isActive()) {
            throw new BusinessRuleException('Solo se pueden anotar socios activos.');
        }

        return DB::transaction(function () use ($tournament, $member, $by, $source) {
            Tournament::query()->lockForUpdate()->findOrFail($tournament->id);

            if ($tournament->participants()->where('member_id', $member->id)->exists()) {
                throw new BusinessRuleException("{$member->fullName()} ya está anotado en el torneo.");
            }

            $fee = bccomp((string) $tournament->price, '0', 2) > 0
                ? $this->fees->createCharge(
                    $member,
                    FeeType::Tournament,
                    $this->concept($tournament),
                    (string) $tournament->price,
                    $this->dueDate($tournament),
                    tournamentId: $tournament->id,
                )
                : null;

            return $tournament->participants()->create([
                'member_id' => $member->id,
                'fee_id' => $fee?->id,
                'source' => $source,
                'is_exception' => ! $this->isEligible($tournament, $member),
                'added_by' => $by?->id,
            ]);
        });
    }

    /** El alumno se anota desde el portal: solo torneos libres, de sus niveles y antes de que empiece. */
    public function selfSignup(Tournament $tournament, Member $member): TournamentParticipant
    {
        $tournament->loadMissing('days');

        if (! $tournament->isSignupOpen()) {
            throw new BusinessRuleException('La inscripción a este torneo está cerrada.');
        }

        if (! $this->isEligible($tournament, $member)) {
            throw new BusinessRuleException('Este torneo es para alumnos de otros '.mb_strtolower(activity_label(true)).'. Consultá con tu profesor.');
        }

        return $this->addParticipant($tournament, $member, null, TournamentParticipant::SOURCE_PORTAL);
    }

    /** Quita a un alumno y anula su cargo. Si ya pagó algo, primero hay que anular el pago. */
    public function removeParticipant(Tournament $tournament, Member $member): void
    {
        $participant = $tournament->participants()->where('member_id', $member->id)->firstOrFail();

        DB::transaction(function () use ($participant) {
            $fee = $participant->fee;
            if ($fee && $fee->status !== FeeStatus::Cancelled) {
                if (bccomp((string) $fee->paid_amount, '0', 2) > 0) {
                    throw new BusinessRuleException('El alumno ya pagó (total o parcialmente) el torneo. Anulá primero el pago.');
                }
                $this->fees->cancel($fee, 'Baja del torneo');
            }

            $participant->delete();
        });
    }

    /**
     * Cancela el torneo y anula los cargos sin pagos.
     *
     * @return int participantes que ya habían pagado algo (para devolverles el dinero)
     */
    public function cancel(Tournament $tournament, string $reason): int
    {
        if ($tournament->isCancelled()) {
            throw new BusinessRuleException('El torneo ya está cancelado.');
        }

        return DB::transaction(function () use ($tournament, $reason) {
            $withPayments = 0;
            foreach ($tournament->fees()->where('status', '!=', FeeStatus::Cancelled)->get() as $fee) {
                if (bccomp((string) $fee->paid_amount, '0', 2) > 0) {
                    $withPayments++;
                } else {
                    $this->fees->cancel($fee, 'Torneo cancelado');
                }
            }

            $tournament->update(['cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $withPayments;
        });
    }

    /** Participantes con el torneo impago (total o parcialmente). */
    public function debtorsQuery(Tournament $tournament): Builder
    {
        return Member::query()->whereHas('fees', fn ($q) => $q
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', FeeStatus::open()));
    }

    /** @return array{participants: int, paid: string, owed: string, debtors: int} */
    public function summary(Tournament $tournament): array
    {
        $totals = $tournament->fees()
            ->where('status', '!=', FeeStatus::Cancelled)
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(amount + surcharge - paid_amount), 0) as owed')
            ->first();

        return [
            'participants' => $tournament->participants()->count(),
            'paid' => (string) $totals->paid,
            'owed' => (string) $totals->owed,
            'debtors' => $this->debtorsQuery($tournament)->count(),
        ];
    }

    private function concept(Tournament $tournament): string
    {
        return 'Torneo '.$tournament->name;
    }

    /** Vence en la fecha máxima de pago; si ya pasó (alumno agregado tarde), hoy. */
    private function dueDate(Tournament $tournament): Carbon
    {
        return $tournament->payment_due_date->isPast() && ! $tournament->payment_due_date->isToday()
            ? today()
            : $tournament->payment_due_date->copy();
    }
}
