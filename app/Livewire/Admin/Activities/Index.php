<?php

namespace App\Livewire\Admin\Activities;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Actividades')]
class Index extends Component
{
    use InteractsWithUi;

    public string $search = '';

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

    public function render()
    {
        return view('livewire.admin.activities.index', [
            'activities' => Activity::query()
                ->with(['instructor', 'schedules'])
                ->withCount('activeEnrollments')
                ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
