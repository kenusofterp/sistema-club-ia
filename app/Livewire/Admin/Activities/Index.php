<?php

namespace App\Livewire\Admin\Activities;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Services\ActivityFeeService;
use App\Services\EnrollmentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Actividades')]
class Index extends Component
{
    use InteractsWithUi;

    public string $search = '';

    // ---- Aumento masivo de cuotas ----
    public bool $showIncrease = false;

    public string $increaseMode = ActivityFeeService::MODE_PERCENT;

    public string $increaseValue = '';

    /** @var array<int, string> */
    public array $increaseIds = [];

    public function delete(int $id): void
    {
        $this->authorize('actividades.gestionar');
        $activity = Activity::withCount('activeEnrollments')->findOrFail($id);

        if ($activity->active_enrollments_count > 0) {
            $this->notify('La actividad tiene inscriptos activos. Desactivala o dalos de baja primero.', 'error');

            return;
        }

        $activity->delete();
        $this->notify('Actividad eliminada.');
    }

    public function openIncrease(): void
    {
        $this->authorize('actividades.gestionar');
        $this->increaseMode = ActivityFeeService::MODE_PERCENT;
        $this->increaseValue = '';
        $this->increaseIds = Activity::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetValidation();
        $this->showIncrease = true;
    }

    public function applyIncrease(ActivityFeeService $service): void
    {
        $this->authorize('actividades.gestionar');
        $this->validate([
            'increaseMode' => 'required|in:'.ActivityFeeService::MODE_AMOUNT.','.ActivityFeeService::MODE_PERCENT,
            'increaseValue' => 'required|numeric|not_in:0',
            'increaseIds' => 'required|array|min:1',
            'increaseIds.*' => org_exists('activities'),
        ], [], ['increaseValue' => 'aumento', 'increaseIds' => mb_strtolower(activity_label(true))]);

        $count = $this->attempt(fn () => $service->increase($this->selectedIds(), $this->increaseMode, $this->normalizedValue()));

        if ($count) {
            $this->showIncrease = false;
            $this->notify("Cuotas actualizadas en {$count} ".mb_strtolower(activity_label(true)).'. Rigen desde la próxima generación.');
        }
    }

    /** Cobra la inscripción anual del año en curso a todos los inscriptos activos del nivel. */
    public function chargeRegistration(int $id, EnrollmentService $service): void
    {
        $this->authorize('inscripciones.gestionar');
        $year = today()->year;
        $created = $this->attempt(fn () => $service->chargeRegistration(Activity::findOrFail($id), $year));

        if ($created !== null) {
            $this->notify($created ? "Se generaron {$created} cargos de inscripción {$year}." : "Todos los inscriptos ya tenían la inscripción {$year}.");
        }
    }

    private function selectedIds(): array
    {
        return array_map('intval', $this->increaseIds);
    }

    private function normalizedValue(): string
    {
        return number_format((float) $this->increaseValue, 2, '.', '');
    }

    public function render(ActivityFeeService $feeService)
    {
        $preview = null;
        if ($this->showIncrease && is_numeric($this->increaseValue) && (float) $this->increaseValue != 0 && $this->increaseIds) {
            try {
                $preview = $feeService->preview($this->selectedIds(), $this->increaseMode, $this->normalizedValue());
            } catch (BusinessRuleException) {
                $preview = null;
            }
        }

        return view('livewire.admin.activities.index', [
            'activities' => Activity::query()
                ->with(['instructor', 'schedules'])
                ->withCount('activeEnrollments')
                ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
            'preview' => $preview,
            'currentYear' => today()->year,
        ]);
    }
}
