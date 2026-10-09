<?php

namespace App\Livewire\Admin\Tournaments;

use App\Enums\FeeStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Services\TournamentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Torneos')]
class Index extends Component
{
    use InteractsWithUi;

    /** proximos | anteriores | cancelados */
    #[Url(as: 'ver')]
    public string $filter = 'proximos';

    public function render(TournamentService $service)
    {
        $tournaments = $service->queryFor(auth()->user())
            ->with(['days.activities'])
            ->withCount('participants')
            ->withSum(['fees as paid_total' => fn ($q) => $q->where('status', '!=', FeeStatus::Cancelled)], 'paid_amount')
            ->withSum(['fees as owed_total' => fn ($q) => $q->whereIn('status', FeeStatus::open())], 'amount')
            ->withSum(['fees as owed_paid' => fn ($q) => $q->whereIn('status', FeeStatus::open())], 'paid_amount')
            ->when($this->filter === 'cancelados', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when($this->filter === 'proximos', fn ($q) => $q->active()->upcoming())
            ->when($this->filter === 'anteriores', fn ($q) => $q->active()->whereDoesntHave('days', fn ($d) => $d->whereDate('date', '>=', today())))
            ->get()
            ->sortBy(fn ($t) => $t->days->first()?->date)
            ->when($this->filter !== 'proximos', fn ($c) => $c->reverse())
            ->values();

        return view('livewire.admin.tournaments.index', ['tournaments' => $tournaments]);
    }
}
