<?php

namespace App\Livewire\Admin\Tournaments;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Member;
use App\Models\Tournament;
use App\Services\RefundService;
use App\Services\TournamentService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Participantes de un torneo: quién pagó, quién debe, agregar (también excepciones) y quitar. */
#[Layout('layouts.admin')]
class Show extends Component
{
    use InteractsWithUi;

    public Tournament $tournament;

    public string $memberSearch = '';

    /** todos | deben | pagaron */
    public string $filter = 'todos';

    public bool $showCancel = false;

    public string $cancelReason = '';

    // ---- Devolución (torneo cancelado) ----
    public bool $showRefund = false;

    public ?int $refundFeeId = null;

    public string $refundAmount = '';

    /** devolver | a_cuenta (saldo a favor) */
    public string $refundDestination = 'devolver';

    public string $refundMethod = 'efectivo';

    public string $refundDate = '';

    public string $refundNotes = '';

    public function mount(Tournament $tournament): void
    {
        abort_unless(app(TournamentService::class)->queryFor(auth()->user())->whereKey($tournament->id)->exists(), 404);
        $this->tournament = $tournament;
    }

    public function add(int $memberId, TournamentService $service): void
    {
        $this->authorize('torneos.gestionar');
        $member = Member::findOrFail($memberId);
        $participant = $this->attempt(fn () => $service->addParticipant($this->tournament, $member, auth()->user()));

        if ($participant) {
            $this->memberSearch = '';
            $this->notify($participant->is_exception
                ? "{$member->fullName()} quedó anotado como excepción (no es de los ".mb_strtolower(activity_label(true)).' del torneo).'
                : "{$member->fullName()} quedó anotado.");
        }
    }

    /** Anota de una vez a todos los alumnos de los niveles del torneo que todavía no están. */
    public function addAllEligible(TournamentService $service): void
    {
        $this->authorize('torneos.gestionar');
        $already = $this->tournament->participants()->pluck('member_id');
        $added = 0;

        foreach ($service->eligibleMembersQuery($this->tournament)->whereNotIn('id', $already)->get() as $member) {
            if ($this->attempt(fn () => $service->addParticipant($this->tournament, $member, auth()->user()))) {
                $added++;
            }
        }

        $this->notify($added ? "Se anotaron {$added} alumnos." : 'Todos los alumnos de los '.mb_strtolower(activity_label(true)).' ya estaban anotados.');
    }

    public function remove(int $memberId, TournamentService $service): void
    {
        $this->authorize('torneos.gestionar');
        $this->attempt(fn () => $service->removeParticipant($this->tournament, Member::withTrashed()->findOrFail($memberId)), 'Participante quitado y cargo anulado.');
    }

    public function cancelTournament(TournamentService $service): void
    {
        $this->authorize('torneos.gestionar');
        $this->validate(['cancelReason' => 'required|string|min:3|max:250'], [], ['cancelReason' => 'motivo']);

        $withPayments = $this->attempt(fn () => $service->cancel($this->tournament, $this->cancelReason));
        if ($withPayments !== null) {
            $this->showCancel = false;
            $this->tournament->refresh();
            $this->notify($withPayments
                ? "Torneo cancelado. {$withPayments} participantes ya habían pagado: si corresponde, registrá la devolución con «Registrar devolución»."
                : 'Torneo cancelado. Se anularon los cargos.');
        }
    }

    public function askRefund(int $feeId, RefundService $refunds): void
    {
        $this->authorize('torneos.gestionar');
        $fee = $this->tournament->fees()->findOrFail($feeId);
        $this->refundFeeId = $fee->id;
        $this->refundAmount = $refunds->refundable($fee);
        $this->refundDestination = 'devolver';
        $this->refundMethod = PaymentMethod::Cash->value;
        $this->refundDate = today()->toDateString();
        $this->refundNotes = '';
        $this->resetValidation();
        $this->showRefund = true;
    }

    /** Al elegir "devolver" o "queda a cuenta", el importe vuelve a ser todo lo que pagó (se puede cambiar). */
    public function updatedRefundDestination(RefundService $refunds): void
    {
        if ($this->refundFeeId && ($fee = $this->tournament->fees()->find($this->refundFeeId))) {
            $this->refundAmount = $refunds->refundable($fee);
        }
    }

    public function confirmRefund(RefundService $refunds): void
    {
        $this->authorize('torneos.gestionar');
        $toCredit = $this->refundDestination === 'a_cuenta';
        $this->validate([
            'refundDestination' => 'required|in:devolver,a_cuenta',
            'refundAmount' => 'required|numeric|min:0.01',
            'refundMethod' => $toCredit ? 'nullable' : ['required', Rule::enum(PaymentMethod::class)],
            'refundDate' => $toCredit ? 'nullable' : 'required|date|before_or_equal:today',
            'refundNotes' => 'nullable|string|max:250',
        ], [], ['refundAmount' => 'importe', 'refundMethod' => 'medio', 'refundDate' => 'fecha', 'refundNotes' => 'nota']);

        $fee = $this->tournament->fees()->findOrFail($this->refundFeeId);
        $amount = number_format((float) $this->refundAmount, 2, '.', '');

        // Queda como saldo a favor para pagar otros cargos (ej. la próxima cuota).
        if ($toCredit) {
            if ($this->attempt(fn () => $refunds->toCredit($fee, $amount, auth()->user(), $this->refundNotes ?: null))) {
                $this->showRefund = false;
                $this->notify(money($amount).' quedaron a cuenta de '.$fee->member->fullName().' (saldo a favor).');
            }

            return;
        }

        $refund = $this->attempt(fn () => $refunds->refund(
            $fee,
            $amount,
            PaymentMethod::from($this->refundMethod),
            auth()->user(),
            Carbon::parse($this->refundDate),
            $this->refundNotes ?: null,
        ));

        if ($refund) {
            $this->showRefund = false;
            $this->notify('Devolución registrada: '.money($refund->amount).' a '.$fee->member->fullName().'.');
        }
    }

    public function render(TournamentService $service)
    {
        $this->tournament->load('days.activities');

        $participants = $this->tournament->participants()
            ->with(['member', 'fee.refunds', 'fee.credits'])
            ->get()
            ->sortBy(fn ($p) => $p->member?->sortableName())
            ->filter(fn ($p) => match ($this->filter) {
                'deben' => $p->fee && $p->fee->isOpen(),
                'pagaron' => ! $p->fee || ! $p->fee->isOpen(),
                default => true,
            })
            ->values();

        $results = strlen($this->memberSearch) >= 2
            ? Member::active()->search($this->memberSearch)
                ->whereNotIn('id', $this->tournament->participants()->select('member_id'))
                ->limit(8)->get()
            : collect();

        $eligibleIds = $results->isNotEmpty()
            ? $service->eligibleMembersQuery($this->tournament)->whereIn('id', $results->pluck('id'))->pluck('id')->all()
            : [];

        return view('livewire.admin.tournaments.show', [
            'participants' => $participants,
            'summary' => $service->summary($this->tournament),
            'results' => $results,
            'eligibleIds' => $eligibleIds,
            'refundPaid' => $this->showRefund && $this->refundFeeId
                ? app(RefundService::class)->refundable($this->tournament->fees()->findOrFail($this->refundFeeId))
                : null,
            'extraCharges' => $this->tournament->extraCharges()
                ->withCount(['fees as charged_count' => fn ($q) => $q->where('status', '!=', FeeStatus::Cancelled)])
                ->withCount(['fees as debtors_count' => fn ($q) => $q->whereIn('status', FeeStatus::open())])
                ->get(),
            'pendingEligible' => $service->eligibleMembersQuery($this->tournament)
                ->whereNotIn('id', $this->tournament->participants()->select('member_id'))->count(),
        ])->title($this->tournament->name);
    }
}
