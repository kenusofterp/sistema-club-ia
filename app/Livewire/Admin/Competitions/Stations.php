<?php

namespace App\Livewire\Admin\Competitions;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Competition;
use App\Models\CompetitionStation;
use App\Models\Organization;
use App\Models\User;
use App\Services\StationService;
use Livewire\Component;

/** Puestos de evaluación de una competencia y sus tribunales de 3 jueces. */
class Stations extends Component
{
    use InteractsWithUi;

    public Competition $competition;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $competition_exercise_id = null;

    public ?int $competition_level_id = null;

    public ?int $competition_group_id = null;

    /** @var array<int, array<int, string|null>> puesto => [asiento => user_id] */
    public array $panels = [];

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
        $this->loadPanels();
    }

    public function create(): void
    {
        $this->resetForm();
        $exercise = $this->competition->exercises()->first();
        $this->competition_exercise_id = $exercise?->id;
        $this->name = $exercise ? $exercise->name().' '.($this->competition->stations()->where('competition_exercise_id', $exercise->id)->count() + 1) : '';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $station = $this->competition->stations()->findOrFail($id);
        $this->editingId = $station->id;
        $this->fill($station->only(['name', 'competition_exercise_id', 'competition_level_id', 'competition_group_id']));
        $this->showForm = true;
    }

    public function updatedCompetitionLevelId(): void
    {
        $this->competition_group_id = null;
    }

    public function save(StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->validate([
            'name' => 'required|string|max:60',
            'competition_exercise_id' => 'required|integer',
            'competition_level_id' => 'nullable|integer',
            'competition_group_id' => 'nullable|integer',
        ], [], ['name' => 'nombre', 'competition_exercise_id' => 'ejercicio']);

        $station = $this->attempt(fn () => $service->save(
            $this->competition,
            $this->editingId ? $this->competition->stations()->findOrFail($this->editingId) : null,
            $this->only(['name', 'competition_exercise_id', 'competition_level_id', 'competition_group_id']),
        ));

        if ($station) {
            $this->showForm = false;
            $this->loadPanels();
            $this->notify($this->editingId ? 'Puesto actualizado.' : 'Puesto creado. Asignale sus 3 jueces.');
            $this->dispatch('competition-changed');
        }
    }

    public function savePanel(int $stationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $station = $this->competition->stations()->findOrFail($stationId);
        $judges = array_map(fn ($id) => $id ? (int) $id : null, $this->panels[$stationId] ?? []);

        if ($this->attempt(fn () => $service->setJudges($station, $judges), 'Tribunal guardado.') === null) {
            $this->loadPanels();
        }
    }

    public function delete(int $id, StationService $service): void
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->delete($this->competition->stations()->findOrFail($id)), 'Puesto eliminado.')) {
            $this->loadPanels();
            $this->dispatch('competition-changed');
        }
    }

    private function loadPanels(): void
    {
        $this->panels = $this->competition->stations()->with('judges')->get()->mapWithKeys(fn (CompetitionStation $station) => [
            $station->id => collect(range(1, CompetitionStation::JUDGES))
                ->mapWithKeys(fn ($seat) => [$seat => (string) ($station->judges->firstWhere('seat', $seat)?->user_id ?? '')])
                ->all(),
        ])->all();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'competition_exercise_id', 'competition_level_id', 'competition_group_id']);
        $this->resetValidation();
    }

    public function render()
    {
        $levels = $this->competition->levels()->with('groups')->get();

        return view('livewire.admin.competitions.stations', [
            'stations' => $this->competition->stations()
                ->with(['competitionExercise.exercise', 'level', 'group', 'judges.user'])
                ->withCount(['judges', 'evaluations'])
                ->get(),
            'exercises' => $this->competition->exercises()->with('exercise')->get(),
            'levels' => $levels,
            'groups' => $this->competition_level_id ? $levels->firstWhere('id', $this->competition_level_id)?->groups ?? collect() : collect(),
            'judgeCandidates' => User::withPermissionIn('notas.cargar', Organization::currentId())->sortBy('name')->values(),
            'editable' => ! $this->competition->isFinished(),
        ]);
    }
}
