<?php

namespace App\Livewire\Admin\ExtraCharges;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Tournament;
use App\Services\ExtraChargeService;
use App\Services\LevelLessonService;
use App\Services\TournamentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Nuevo adicional (vestimenta / artículo). Se elige para qué es (un torneo, un nivel o alumnos sueltos)
 * y se cobra a los alumnos tildados: en torneo y nivel aparecen todos tildados y se destilda a quien no va.
 */
#[Layout('layouts.admin')]
#[Title('Nuevo adicional')]
class Form extends Component
{
    use InteractsWithUi;

    /** torneo | nivel | alumnos */
    public string $origin = 'torneo';

    #[Url(as: 'torneo')]
    public ?int $tournamentId = null;

    #[Url(as: 'nivel')]
    public ?int $activityId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $due_date = '';

    public string $search = '';

    /** @var array<int, string> alumnos a los que se les cobra */
    public array $selected = [];

    public function mount(): void
    {
        $this->origin = match (true) {
            (bool) $this->tournamentId => 'torneo',
            (bool) $this->activityId => 'nivel',
            default => 'torneo',
        };
        $this->due_date = today()->addDays(7)->toDateString();
        $this->applyContext();
    }

    public function updatedOrigin(): void
    {
        $this->applyContext();
    }

    public function updatedTournamentId(): void
    {
        $this->applyContext();
    }

    public function updatedActivityId(): void
    {
        $this->applyContext();
    }

    /** Al cambiar el origen: todos tildados (torneo / nivel) o ninguno (alumnos sueltos). */
    private function applyContext(): void
    {
        if ($this->origin === 'torneo' && ($tournament = $this->tournament())) {
            // Por defecto, la fecha de pago del torneo; nunca después de que empiece el torneo.
            $due = $tournament->payment_due_date->lt(today()) ? today() : $tournament->payment_due_date->copy();
            $start = $tournament->startsOn();
            $this->due_date = ($start && $due->gt($start) ? $start : $due)->toDateString();
        }

        $this->selected = $this->origin === 'alumnos'
            ? []
            : $this->candidates()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function selectAll(): void
    {
        $this->selected = $this->candidates()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function selectNone(): void
    {
        $this->selected = [];
    }

    private function tournament(): ?Tournament
    {
        return $this->tournamentId
            ? app(TournamentService::class)->queryFor(auth()->user())->active()->find($this->tournamentId)
            : null;
    }

    private function activity(): ?Activity
    {
        if (! $this->activityId) {
            return null;
        }
        $user = auth()->user();
        $allowed = $user->can('agenda.todas') || in_array($this->activityId, app(LevelLessonService::class)->activityIdsTaughtBy($user), true);

        return $allowed ? Activity::find($this->activityId) : null;
    }

    private function candidates()
    {
        $service = app(ExtraChargeService::class);
        $user = auth()->user();

        return match ($this->origin) {
            'torneo' => $this->tournament() ? $service->candidatesQuery($user, tournament: $this->tournament())->orderBy('last_name')->orderBy('first_name')->get() : collect(),
            'nivel' => $this->activity() ? $service->candidatesQuery($user, activity: $this->activity())->orderBy('last_name')->orderBy('first_name')->get() : collect(),
            default => $service->candidatesQuery($user)->orderBy('last_name')->orderBy('first_name')->get(),
        };
    }

    public function save(ExtraChargeService $service)
    {
        $this->authorize('articulos.gestionar');
        $this->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:99999999',
            'due_date' => 'required|date',
            'origin' => 'required|in:torneo,nivel,alumnos',
            'tournamentId' => 'required_if:origin,torneo|nullable|integer',
            'activityId' => 'required_if:origin,nivel|nullable|integer',
            'selected' => 'required|array|min:1',
        ], [
            'tournamentId.required_if' => 'Elegí el torneo.',
            'activityId.required_if' => 'Elegí el '.mb_strtolower(activity_label()).'.',
            'selected.required' => 'Tildá al menos un alumno.',
            'selected.min' => 'Tildá al menos un alumno.',
        ], ['name' => 'nombre', 'price' => 'precio', 'due_date' => 'vencimiento']);

        // Solo alumnos permitidos para este origen.
        $members = $this->candidates()->whereIn('id', array_map('intval', $this->selected))->values();

        $charge = $this->attempt(fn () => $service->create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'due_date' => $this->due_date,
            'tournament_id' => $this->origin === 'torneo' ? $this->tournament()?->id : null,
            'activity_id' => $this->origin === 'nivel' ? $this->activity()?->id : null,
        ], $members, auth()->user()));

        if ($charge) {
            session()->flash('success', "Se cargó {$charge->name} a {$members->count()} alumnos.");

            return $this->redirectRoute('admin.extra-charges.show', $charge, navigate: true);
        }

        return null;
    }

    public function render(TournamentService $tournaments)
    {
        $user = auth()->user();
        $mine = $user->can('agenda.todas') ? null : app(LevelLessonService::class)->activityIdsTaughtBy($user);
        $candidates = $this->candidates();

        return view('livewire.admin.extra-charges.form', [
            'tournaments' => $tournaments->queryFor($user)->active()->upcoming()->with('days')->get()->sortBy(fn ($t) => $t->startsOn())->values(),
            'activities' => Activity::where('is_active', true)->when($mine !== null, fn ($q) => $q->whereIn('id', $mine))->orderBy('name')->get(['id', 'name']),
            'candidates' => $this->search
                ? $candidates->filter(fn (Member $m) => str_contains(mb_strtolower($m->sortableName()), mb_strtolower($this->search)))->values()
                : $candidates,
            'candidateCount' => $candidates->count(),
            'maxDueDate' => $this->origin === 'torneo' ? $this->tournament()?->startsOn()?->toDateString() : null,
        ]);
    }
}
