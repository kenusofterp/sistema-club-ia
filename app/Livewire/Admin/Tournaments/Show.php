<?php

namespace App\Livewire\Admin\Tournaments;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Member;
use App\Models\Tournament;
use App\Services\TournamentService;
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
                ? "Torneo cancelado. {$withPayments} participantes ya habían pagado: anulá esos pagos o devolvé el dinero."
                : 'Torneo cancelado. Se anularon los cargos.');
        }
    }

    public function render(TournamentService $service)
    {
        $this->tournament->load('days.activities');

        $participants = $this->tournament->participants()
            ->with(['member', 'fee'])
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
            'pendingEligible' => $service->eligibleMembersQuery($this->tournament)
                ->whereNotIn('id', $this->tournament->participants()->select('member_id'))->count(),
        ])->title($this->tournament->name);
    }
}
