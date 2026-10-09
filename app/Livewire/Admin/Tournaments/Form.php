<?php

namespace App\Livewire\Admin\Tournaments;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\Tournament;
use App\Services\LevelLessonService;
use App\Services\TournamentService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use InteractsWithUi;

    public ?Tournament $tournament = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $payment_due_date = '';

    /** libre | elegidos */
    public string $access = 'libre';

    /** @var array<int, array{date: string, notes: string, activity_ids: array<int, string>}> */
    public array $days = [];

    public function mount(?Tournament $tournament = null): void
    {
        if ($tournament?->exists) {
            abort_unless(app(TournamentService::class)->queryFor(auth()->user())->whereKey($tournament->id)->exists(), 404);
            $this->tournament = $tournament->load('days.activities');
            $this->name = $tournament->name;
            $this->description = (string) $tournament->description;
            $this->price = (string) $tournament->price;
            $this->payment_due_date = $tournament->payment_due_date->toDateString();
            $this->access = $tournament->is_open ? 'libre' : 'elegidos';
            $this->days = $tournament->days->map(fn ($day) => [
                'date' => $day->date->toDateString(),
                'notes' => (string) $day->notes,
                'activity_ids' => $day->activities->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ])->all();
        } else {
            $this->addDay();
        }
    }

    public function addDay(): void
    {
        $last = collect($this->days)->pluck('date')->filter()->max();
        $this->days[] = [
            'date' => $last ? Carbon::parse($last)->addDay()->toDateString() : '',
            'notes' => '',
            'activity_ids' => [],
        ];
    }

    public function removeDay(int $index): void
    {
        unset($this->days[$index]);
        $this->days = array_values($this->days);
    }

    public function save(TournamentService $service)
    {
        $this->authorize('torneos.gestionar');

        $data = $this->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0|max:99999999',
            'payment_due_date' => 'required|date',
            'access' => 'required|in:libre,elegidos',
            'days' => 'required|array|min:1|max:31',
            'days.*.date' => 'required|date',
            'days.*.notes' => 'nullable|string|max:200',
            'days.*.activity_ids' => 'required|array|min:1',
            'days.*.activity_ids.*' => org_exists('activities'),
        ], [
            'days.*.activity_ids.required' => 'Elegí al menos un '.mb_strtolower(activity_label()).' para este día.',
        ], [
            'price' => 'costo',
            'payment_due_date' => 'fecha máxima de pago',
            'days.*.date' => 'fecha',
        ]);

        $tournament = $this->attempt(fn () => $service->save(
            $this->tournament,
            [...$data, 'price' => number_format((float) $data['price'], 2, '.', ''), 'is_open' => $this->access === 'libre'],
            $this->days,
            auth()->user(),
        ));

        if ($tournament) {
            session()->flash('success', 'Torneo guardado.');

            return $this->redirectRoute('admin.tournaments.show', $tournament, navigate: true);
        }

        return null;
    }

    public function render()
    {
        $user = auth()->user();
        $mine = $user->can('agenda.todas') ? null : app(LevelLessonService::class)->activityIdsTaughtBy($user);

        return view('livewire.admin.tournaments.form', [
            'activities' => Activity::query()
                ->where('is_active', true)
                ->when($mine !== null, fn ($q) => $q->whereIn('id', $mine))
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->title($this->tournament ? 'Editar torneo' : 'Nuevo torneo');
    }
}
