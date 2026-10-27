<?php

namespace App\Livewire\Admin\Competitions;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Competition;
use App\Models\Exercise;
use App\Models\Tournament;
use App\Services\CompetitionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Alta/edición de una competencia: datos, niveles con sus grupos y ejercicios que se evalúan. */
#[Layout('layouts.admin')]
class Form extends Component
{
    use InteractsWithUi;

    public ?Competition $competition = null;

    public string $name = '';

    public string $date = '';

    public string $location = '';

    public ?int $tournament_id = null;

    /** @var array<int, array{id: ?int, name: string, groups: array<int, array{id: ?int, name: string}>}> */
    public array $levels = [];

    /** @var array<int, int> */
    public array $exerciseIds = [];

    public function mount(?Competition $competition = null): void
    {
        if ($competition?->exists) {
            abort_if($competition->isFinished(), 403, 'La competencia está finalizada.');
            $this->competition = $competition->load(['levels.groups', 'exercises']);
            $this->name = $competition->name;
            $this->date = $competition->date->toDateString();
            $this->location = (string) $competition->location;
            $this->tournament_id = $competition->tournament_id;
            $this->levels = $competition->levels->map(fn ($level) => [
                'id' => $level->id,
                'name' => $level->name,
                'groups' => $level->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->all(),
            ])->all();
            $this->exerciseIds = $competition->exercises->pluck('exercise_id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->competition = null;
            $this->date = today()->toDateString();
            $this->levels = [['id' => null, 'name' => 'Nivel 1', 'groups' => []]];
        }
    }

    public function addLevel(): void
    {
        $this->levels[] = ['id' => null, 'name' => 'Nivel '.(count($this->levels) + 1), 'groups' => []];
    }

    public function removeLevel(int $index): void
    {
        unset($this->levels[$index]);
        $this->levels = array_values($this->levels);
    }

    public function addGroup(int $level): void
    {
        $count = count($this->levels[$level]['groups']);
        $this->levels[$level]['groups'][] = ['id' => null, 'name' => 'Grupo '.chr(65 + min($count, 25))];
    }

    public function removeGroup(int $level, int $index): void
    {
        unset($this->levels[$level]['groups'][$index]);
        $this->levels[$level]['groups'] = array_values($this->levels[$level]['groups']);
    }

    public function save(CompetitionService $service)
    {
        $this->authorize('competencias.gestionar');

        $this->validate([
            'name' => 'required|string|max:150',
            'date' => 'required|date',
            'location' => 'nullable|string|max:200',
            'tournament_id' => ['nullable', 'integer', org_exists('tournaments')],
            'levels' => 'required|array|min:1',
            'levels.*.name' => 'required|string|max:80',
            'levels.*.groups.*.name' => 'required|string|max:80',
            'exerciseIds' => 'required|array|min:1',
            'exerciseIds.*' => ['integer', org_exists('exercises')],
        ], [], [
            'name' => 'nombre',
            'date' => 'fecha',
            'location' => 'lugar',
            'levels' => 'niveles',
            'levels.*.name' => 'nombre del nivel',
            'levels.*.groups.*.name' => 'nombre del grupo',
            'exerciseIds' => 'ejercicios',
        ]);

        $competition = $this->attempt(fn () => $service->save(
            $this->competition,
            ['name' => $this->name, 'date' => $this->date, 'location' => $this->location ?: null, 'tournament_id' => $this->tournament_id ?: null],
            $this->levels,
            $this->exerciseIds,
            auth()->user(),
        ));

        if ($competition) {
            session()->flash('success', $this->competition ? 'Competencia actualizada.' : 'Competencia creada. Ahora armá los puestos con sus jueces e inscribí a las participantes.');

            return $this->redirectRoute('admin.competitions.show', $competition, navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.admin.competitions.form', [
            'exercises' => Exercise::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->exerciseIds ?: [0]))
                ->withCount('criteria')
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
            'tournaments' => Tournament::active()->latest('id')->limit(50)->get(['id', 'name']),
        ])->title($this->competition ? 'Editar '.$this->competition->name : 'Nueva competencia');
    }
}
