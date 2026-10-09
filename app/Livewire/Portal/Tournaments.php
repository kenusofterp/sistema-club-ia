<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Tournament;
use App\Services\TournamentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Torneos del alumno: en los que está anotado y los libres de sus niveles a los que se puede anotar. */
#[Layout('layouts.portal')]
#[Title('Torneos')]
class Tournaments extends Component
{
    use ForCurrentMember, InteractsWithUi;

    public function join(int $tournamentId, TournamentService $service): void
    {
        $tournament = Tournament::active()->findOrFail($tournamentId);

        $this->attempt(
            fn () => $service->selfSignup($tournament, $this->member()),
            "¡Te anotaste en {$tournament->name}! El costo se sumó a tu cuenta."
        );
    }

    public function render(TournamentService $service)
    {
        $member = $this->member();
        $activityIds = $member->enrollments()->where('status', 'activa')->pluck('activity_id');

        $mine = Tournament::query()
            ->whereHas('participants', fn ($q) => $q->where('member_id', $member->id))
            ->with(['days.activities', 'fees' => fn ($q) => $q->where('member_id', $member->id)->where('status', '!=', 'anulada')])
            ->get()
            ->sortByDesc(fn ($t) => $t->days->first()?->date)
            ->values();

        $available = Tournament::active()->upcoming()
            ->where('is_open', true)
            ->whereHas('days.activities', fn ($q) => $q->whereIn('activities.id', $activityIds))
            ->whereDoesntHave('participants', fn ($q) => $q->where('member_id', $member->id))
            ->with('days.activities')
            ->get()
            ->filter(fn (Tournament $t) => $t->isSignupOpen())
            ->sortBy(fn ($t) => $t->days->first()?->date)
            ->values();

        return view('livewire.portal.tournaments', [
            'mine' => $mine,
            'available' => $available,
        ]);
    }
}
