<?php

namespace App\Livewire\Admin\Competitions;

use App\Enums\EvaluationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionStation;
use App\Models\Evaluation;
use App\Services\StationService;
use Livewire\Component;

/**
 * Colas de pasada por puesto: asignar participantes (a mano o automáticamente), reordenar arrastrando,
 * marcar ausente, mandar al final o pasar a otro puesto del mismo ejercicio. Se refresca sola.
 */
class Queues extends Component
{
    use InteractsWithUi;

    public Competition $competition;

    /** @var array<int, string> puesto => entry a asignar */
    public array $toAssign = [];

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
    }

    public function assign(int $stationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $station = $this->station($stationId);
        $entry = $this->competition->entries()->find((int) ($this->toAssign[$stationId] ?? 0));

        if (! $entry) {
            $this->notify('Elegí una participante.', 'error');

            return;
        }

        if ($this->attempt(fn () => $service->assign($station, $entry))) {
            $this->toAssign[$stationId] = '';
            $this->dispatch('competition-changed');
        }
    }

    public function assignPending(StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $count = $this->attempt(fn () => $service->assignPending($this->competition));

        if ($count !== null) {
            $this->notify($count ? "Se asignaron {$count} pasadas." : 'No había participantes sin puesto (o no hay puestos compatibles con su nivel).');
            $this->dispatch('competition-changed');
        }
    }

    /** wire:sort: $item = pasada movida, $position = nuevo índice entre las pendientes, $stationId = puesto. */
    public function sortQueue(int $item, int $position, int $stationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $station = $this->station($stationId);

        // Mismo criterio que los ítems arrastrables de la vista: pendientes que todavía no empezaron.
        $pending = $station->evaluations()->where('status', EvaluationStatus::Pending)->doesntHave('sheets')
            ->pluck('id')->reject(fn ($id) => $id === $item)->values();
        $pending->splice(max(0, $position), 0, [$item]);

        $this->attempt(fn () => $service->reorder($station, $pending->all()));
    }

    public function absent(int $evaluationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->attempt(fn () => $service->markAbsent($this->evaluation($evaluationId)), 'Marcada como ausente.');
    }

    public function restore(int $evaluationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->attempt(fn () => $service->restore($this->evaluation($evaluationId)), 'Volvió a la cola (al final).');
    }

    public function toEnd(int $evaluationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->attempt(fn () => $service->sendToEnd($this->evaluation($evaluationId)));
    }

    public function move(int $evaluationId, int $stationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->attempt(fn () => $service->move($this->evaluation($evaluationId), $this->station($stationId)), 'Pasada movida de puesto.');
    }

    public function unassign(int $evaluationId, StationService $service): void
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->unassign($this->evaluation($evaluationId)), 'Quitada de la cola.')) {
            $this->dispatch('competition-changed');
        }
    }

    private function station(int $id): CompetitionStation
    {
        return $this->competition->stations()->findOrFail($id);
    }

    private function evaluation(int $id): Evaluation
    {
        return $this->competition->evaluations()->findOrFail($id);
    }

    public function render(StationService $service)
    {
        $stations = $this->competition->stations()
            ->with(['competitionExercise', 'level', 'group', 'evaluations.entry.competitor', 'evaluations.sheets'])
            ->get();

        $entries = $this->competition->entries()->with(['competitor', 'evaluations'])->get()
            ->sortBy(fn (CompetitionEntry $e) => mb_strtolower($e->competitor->last_name));

        // Para cada puesto: inscriptas compatibles que todavía no tienen puesto en ese ejercicio.
        $assignable = $stations->mapWithKeys(fn (CompetitionStation $station) => [
            $station->id => $entries->filter(fn (CompetitionEntry $e) => $service->fits($station, $e)
                && ! $e->evaluations->contains('competition_exercise_id', $station->competition_exercise_id))->values(),
        ]);

        return view('livewire.admin.competitions.queues', [
            'stations' => $stations,
            'assignable' => $assignable,
            'unassignedCount' => $assignable->sum(fn ($list) => $list->count()),
            'editable' => ! $this->competition->isFinished(),
        ]);
    }
}
