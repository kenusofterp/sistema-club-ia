<?php

namespace App\Livewire\Admin\Exercises;

use App\Enums\CriterionType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Exercise;
use App\Services\ExerciseService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ejercicios de competencia (Salto, Suelo, Viga…) y su plantilla de criterios. */
#[Layout('layouts.admin')]
#[Title('Ejercicios y criterios')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var array<int, array{id: ?int, name: string, type: string, min: string, max: string, decimals: int|string, weight: string}> */
    public array $criteria = [];

    public function create(): void
    {
        $this->resetForm();
        $this->addCriterion();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $exercise = Exercise::with('criteria')->findOrFail($id);
        $this->editingId = $exercise->id;
        $this->name = $exercise->name;
        $this->description = (string) $exercise->description;
        $this->is_active = $exercise->is_active;
        $this->criteria = $exercise->criteria->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type->value,
            'min' => $this->trim($c->min),
            'max' => $this->trim($c->max),
            'decimals' => $c->decimals,
            'weight' => $this->trim($c->weight),
        ])->all();
        $this->showForm = true;
    }

    public function addCriterion(string $type = 'puntaje'): void
    {
        $this->criteria[] = ['id' => null, 'name' => '', 'type' => $type, 'min' => '0', 'max' => $type === 'deduccion' ? '2' : '10', 'decimals' => 1, 'weight' => '1'];
    }

    public function removeCriterion(int $index): void
    {
        unset($this->criteria[$index]);
        $this->criteria = array_values($this->criteria);
    }

    public function moveCriterion(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (isset($this->criteria[$index], $this->criteria[$target])) {
            [$this->criteria[$index], $this->criteria[$target]] = [$this->criteria[$target], $this->criteria[$index]];
        }
    }

    public function save(ExerciseService $service): void
    {
        $this->authorize('competencias.gestionar');

        $this->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:250',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:80',
            'criteria.*.type' => ['required', Rule::enum(CriterionType::class)],
            'criteria.*.min' => 'required|numeric|min:-9999|max:9999',
            'criteria.*.max' => 'required|numeric|min:-9999|max:9999',
            'criteria.*.decimals' => 'required|integer|min:0|max:3',
            'criteria.*.weight' => 'required|numeric|gt:0|max:999',
        ], [], [
            'name' => 'nombre',
            'criteria' => 'criterios',
            'criteria.*.name' => 'nombre del criterio',
            'criteria.*.min' => 'mínimo',
            'criteria.*.max' => 'máximo',
            'criteria.*.decimals' => 'decimales',
            'criteria.*.weight' => 'peso',
        ]);

        $saved = $this->attempt(fn () => $service->save(
            $this->editingId ? Exercise::findOrFail($this->editingId) : null,
            ['name' => $this->name, 'description' => $this->description ?: null, 'is_active' => $this->is_active],
            $this->criteria,
        ));

        if ($saved) {
            $this->showForm = false;
            $this->notify($this->editingId ? 'Ejercicio actualizado. Las competencias ya iniciadas siguen con su plantilla original.' : 'Ejercicio creado.');
        }
    }

    public function delete(int $id, ExerciseService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->attempt(fn () => $service->delete(Exercise::findOrFail($id)), 'Ejercicio eliminado.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'is_active', 'criteria']);
        $this->resetValidation();
    }

    private function trim(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }

    public function render()
    {
        return view('livewire.admin.exercises.index', [
            'exercises' => Exercise::with('criteria')->withCount('criteria')->orderBy('sort_order')->orderBy('name')->get(),
            'types' => CriterionType::options(),
        ]);
    }
}
