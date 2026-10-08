<?php

namespace App\Livewire\Admin\Gym;

use App\Enums\PlanAccessType;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Planes')]
class Plans extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $duration_unit = 'meses';

    public int $duration_value = 1;

    public string $access_type = 'libre';

    public ?int $visit_limit = null;

    public string $visit_period = 'mes';

    /** @var array<int, array{days: array<int, string>, from: string, to: string}> */
    public array $windows = [];

    /** @var array<int, string> */
    public array $activityIds = [];

    public bool $is_featured = false;

    public bool $is_public = true;

    public bool $is_active = true;

    public int $sort_order = 0;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $plan = Plan::with('activities')->findOrFail($id);
        $this->editingId = $plan->id;
        $this->fill([
            'name' => $plan->name,
            'description' => (string) $plan->description,
            'price' => (string) $plan->price,
            'duration_unit' => $plan->duration_unit,
            'duration_value' => $plan->duration_value,
            'access_type' => $plan->access_type->value,
            'visit_limit' => $plan->visit_limit,
            'visit_period' => $plan->visit_period,
            'is_featured' => $plan->is_featured,
            'is_public' => $plan->is_public,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
        ]);
        $this->windows = collect($plan->access_windows ?? [])
            ->map(fn ($w) => ['days' => array_map('strval', $w['days'] ?? []), 'from' => $w['from'], 'to' => $w['to']])
            ->all();
        $this->activityIds = $plan->activities->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function addWindow(): void
    {
        $this->windows[] = ['days' => ['1', '2', '3', '4', '5'], 'from' => '06:00', 'to' => '14:00'];
    }

    public function removeWindow(int $index): void
    {
        unset($this->windows[$index]);
        $this->windows = array_values($this->windows);
    }

    public function save(): void
    {
        $this->authorize('planes.gestionar');

        $data = $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0|max:99999999',
            'duration_unit' => ['required', Rule::in(array_keys(Plan::DURATION_UNITS))],
            'duration_value' => 'required|integer|min:1|max:366',
            'access_type' => ['required', Rule::enum(PlanAccessType::class)],
            'visit_limit' => 'nullable|integer|min:1|max:1000',
            'visit_period' => ['required', Rule::in(array_keys(Plan::VISIT_PERIODS))],
            'windows' => 'array|max:10',
            'windows.*.days' => 'required|array|min:1',
            'windows.*.days.*' => 'integer|between:1,7',
            'windows.*.from' => 'required|date_format:H:i',
            'windows.*.to' => 'required|date_format:H:i|after:windows.*.from',
            'activityIds' => 'array',
            'activityIds.*' => 'exists:activities,id',
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ], [
            'windows.*.days.required' => 'Elegí al menos un día para cada franja.',
        ], [
            'windows.*.from' => 'hora desde',
            'windows.*.to' => 'hora hasta',
        ]);

        if (PlanAccessType::from($this->access_type)->includesClasses() && $this->activityIds === []) {
            $this->addError('activityIds', 'Seleccioná las clases incluidas en el plan.');

            return;
        }

        $windows = collect($data['windows'])->map(fn ($w) => [
            'days' => array_map('intval', $w['days']),
            'from' => $w['from'],
            'to' => $w['to'],
        ])->values()->all();

        DB::transaction(function () use ($data, $windows) {
            $plan = Plan::updateOrCreate(['id' => $this->editingId], [
                ...collect($data)->except(['windows', 'activityIds'])->all(),
                'description' => $data['description'] ?: null,
                'access_windows' => $windows ?: null,
            ]);
            $plan->activities()->sync(PlanAccessType::from($this->access_type)->includesClasses() ? $this->activityIds : []);
        });

        $this->showForm = false;
        $this->notify('Plan guardado. Los cambios de precio aplican a nuevas contrataciones y renovaciones.');
    }

    public function delete(int $id): void
    {
        $this->authorize('planes.gestionar');
        $plan = Plan::findOrFail($id);

        if ($plan->subscriptions()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])->exists()) {
            $this->notify('El plan tiene socios con suscripciones vigentes. Desactivalo en lugar de eliminarlo.', 'error');

            return;
        }

        $plan->delete();
        $this->notify('Plan eliminado.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'price', 'duration_unit', 'duration_value', 'access_type', 'visit_limit', 'visit_period', 'windows', 'activityIds', 'is_featured', 'is_public', 'is_active', 'sort_order']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.gym.plans', [
            'plans' => Plan::with('activities')
                ->withCount(['subscriptions as active_count' => fn ($q) => $q->current()])
                ->orderBy('sort_order')->orderBy('price')->get(),
            'activities' => Activity::active()->get(['id', 'name']),
            'accessTypes' => PlanAccessType::options(),
        ]);
    }
}
