<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\ExtraCharge;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vestimenta / artículos que el profesor compra y cobra a los alumnos que elige (de un torneo o sueltos).
 * Cada alumno tiene su cargo de la entidad: se paga como la cuota (efectivo, transferencia o secretaría),
 * vence sin recargo ni bloqueo de ingreso, y se le puede anular a un alumno puntual.
 */
class ExtraChargeService
{
    public function __construct(
        private FeeService $fees,
        private TournamentService $tournaments,
        private CashCollectionService $cash,
    ) {}

    /**
     * @param  array{name: string, description?: ?string, price: string, due_date: string, tournament_id?: ?int, activity_id?: ?int}  $data
     * @param  Collection<int, Member>  $members
     */
    public function create(array $data, Collection $members, User $by): ExtraCharge
    {
        if ($members->isEmpty()) {
            throw new BusinessRuleException('Elegí al menos un alumno.');
        }

        $this->assertPrice($data['price']);
        $this->assertDueDate($data['due_date'], isset($data['tournament_id']) ? Tournament::find($data['tournament_id']) : null);

        return DB::transaction(function () use ($data, $members, $by) {
            $charge = ExtraCharge::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'due_date' => $data['due_date'],
                'tournament_id' => $data['tournament_id'] ?? null,
                'activity_id' => $data['activity_id'] ?? null,
                'created_by' => $by->id,
            ]);

            $this->chargeMembers($charge, $members);

            return $charge;
        });
    }

    /**
     * Se lo cobra también a otros alumnos (o de nuevo a quien se le había anulado).
     *
     * @param  Collection<int, Member>  $members
     * @return int cargos creados
     */
    public function addMembers(ExtraCharge $charge, Collection $members): int
    {
        return DB::transaction(fn () => $this->chargeMembers($charge, $members));
    }

    private function chargeMembers(ExtraCharge $charge, Collection $members): int
    {
        $already = $charge->fees()->where('status', '!=', FeeStatus::Cancelled)->pluck('member_id')->all();
        $created = 0;

        foreach ($members->unique('id') as $member) {
            if (in_array($member->id, $already, true)) {
                continue;
            }

            $this->fees->createCharge(
                $member,
                FeeType::Item,
                $this->concept($charge),
                (string) $charge->price,
                $this->dueDate($charge),
                extraChargeId: $charge->id,
            );
            $created++;
        }

        return $created;
    }

    /** Anula el cargo de un alumno (ej. "la consiguió por otro lado"). Si ya pagó algo, primero hay que anular el pago. */
    public function cancelFor(ExtraCharge $charge, Member $member, string $reason): void
    {
        $fee = $charge->fees()->where('member_id', $member->id)->where('status', '!=', FeeStatus::Cancelled)->first();

        if (! $fee) {
            throw new BusinessRuleException('Ese alumno no tiene este cargo activo.');
        }

        if (bccomp((string) $fee->paid_amount, '0', 2) > 0) {
            throw new BusinessRuleException('El alumno ya pagó (total o parcialmente). Anulá primero el pago.');
        }

        $this->fees->cancel($fee, $reason);
    }

    /** Cambia nombre, precio o vencimiento; se actualizan los cargos que todavía no tienen pagos. */
    public function update(ExtraCharge $charge, array $data): ExtraCharge
    {
        $this->assertPrice($data['price']);
        $this->assertDueDate($data['due_date'], $charge->tournament);

        return DB::transaction(function () use ($charge, $data) {
            $charge->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'due_date' => $data['due_date'],
            ]);

            $charge->fees()
                ->whereIn('status', FeeStatus::open())
                ->where('paid_amount', 0)
                ->get()
                ->each(function (Fee $fee) use ($charge) {
                    $fee->fill([
                        'amount' => $charge->price,
                        'concept' => $this->concept($charge),
                        'due_date' => $this->dueDate($charge),
                    ]);
                    $fee->status = $fee->resolveStatus();
                    $fee->save();
                });

            return $charge;
        });
    }

    /** Adicionales que ve un usuario: todos con agenda.todas; si no, los que creó y los de sus torneos. */
    public function queryFor(User $user): Builder
    {
        if ($user->can('agenda.todas')) {
            return ExtraCharge::query();
        }

        $tournamentIds = $this->tournaments->queryFor($user)->pluck('id');

        return ExtraCharge::query()->where(fn ($q) => $q
            ->where('created_by', $user->id)
            ->orWhereIn('tournament_id', $tournamentIds));
    }

    /**
     * A quiénes se les puede cobrar: participantes del torneo, alumnos del nivel o, si no, los alumnos
     * del profesor (todos los socios activos para quien ve toda la agenda).
     */
    public function candidatesQuery(User $user, ?Tournament $tournament = null, ?Activity $activity = null): Builder
    {
        if ($tournament) {
            return Member::query()->whereHas('tournamentParticipations', fn ($q) => $q->where('tournament_id', $tournament->id));
        }

        $base = $user->can('agenda.todas') ? Member::active() : $this->cash->studentsQuery($user)->active();

        return $activity
            ? $base->whereHas('enrollments', fn ($q) => $q->where('activity_id', $activity->id)->where('status', EnrollmentStatus::Active))
            : $base;
    }

    /** Alumnos con el adicional impago (total o parcialmente). */
    public function debtorsQuery(ExtraCharge $charge): Builder
    {
        return Member::query()->whereHas('fees', fn ($q) => $q
            ->where('extra_charge_id', $charge->id)
            ->whereIn('status', FeeStatus::open()));
    }

    /** @return array{charged: int, paid: string, owed: string, cancelled: int, debtors: int} */
    public function summary(ExtraCharge $charge): array
    {
        $active = $charge->fees()->where('status', '!=', FeeStatus::Cancelled);
        $totals = (clone $active)
            ->selectRaw('COUNT(*) as charged, COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(amount + surcharge - paid_amount), 0) as owed')
            ->first();

        return [
            'charged' => (int) $totals->charged,
            'paid' => (string) $totals->paid,
            'owed' => (string) $totals->owed,
            'cancelled' => $charge->fees()->where('status', FeeStatus::Cancelled)->distinct('member_id')->count('member_id'),
            'debtors' => $this->debtorsQuery($charge)->count(),
        ];
    }

    private function concept(ExtraCharge $charge): string
    {
        return $charge->name.' - '.$charge->contextLabel();
    }

    /** Vence en la fecha indicada; si ya pasó (alumno agregado tarde), hoy. */
    private function dueDate(ExtraCharge $charge): Carbon
    {
        return $charge->due_date->lt(today()) ? today() : $charge->due_date->copy();
    }

    /** La vestimenta de un torneo se tiene que pagar a más tardar el día que empieza el torneo. */
    private function assertDueDate(string $dueDate, ?Tournament $tournament): void
    {
        $start = $tournament?->startsOn();

        if ($start && Carbon::parse($dueDate)->gt($start)) {
            throw new BusinessRuleException('La fecha de pago no puede ser posterior al torneo ('.$start->format('d/m/Y').').');
        }
    }

    private function assertPrice(string $price): void
    {
        if (bccomp($price, '0', 2) <= 0) {
            throw new BusinessRuleException('El precio tiene que ser mayor a cero.');
        }
    }
}
