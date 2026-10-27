<?php

namespace App\Livewire\Admin\ScoreChanges;

use App\Enums\ScoreChangeStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\CompetitionCriterion;
use App\Models\ScoreChangeRequest;
use App\Services\ScoreChangeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pedidos de modificación de notas confirmadas: autorizar o rechazar, e historial completo. */
#[Layout('layouts.admin')]
#[Title('Modificaciones de notas')]
class Index extends Component
{
    use InteractsWithUi;

    #[Url(as: 'ver')]
    public string $filter = 'pendientes';

    /** @var array<int, string> pedido => nota de quien resuelve */
    public array $notes = [];

    public function approve(int $id, ScoreChangeService $service): void
    {
        $this->authorize('notas.autorizar');
        $this->attempt(fn () => $service->approve(ScoreChangeRequest::findOrFail($id), auth()->user(), $this->notes[$id] ?? null),
            'Modificación aplicada: se recalcularon el puntaje y los rankings.');
    }

    public function reject(int $id, ScoreChangeService $service): void
    {
        $this->authorize('notas.autorizar');
        $this->attempt(fn () => $service->reject(ScoreChangeRequest::findOrFail($id), auth()->user(), $this->notes[$id] ?? null), 'Pedido rechazado.');
    }

    public function render()
    {
        $requests = ScoreChangeRequest::query()
            ->with(['competition', 'evaluation.entry.competitor', 'evaluation.competitionExercise', 'evaluation.station', 'sheet.user', 'requester', 'resolver'])
            ->when($this->filter === 'pendientes', fn ($q) => $q->where('status', ScoreChangeStatus::Pending))
            ->latest()
            ->limit(100)
            ->get();

        $criteria = CompetitionCriterion::whereIn('competition_exercise_id', $requests->pluck('evaluation.competition_exercise_id')->unique())
            ->get()->keyBy('id');

        return view('livewire.admin.score-changes.index', [
            'requests' => $requests,
            'criteria' => $criteria,
            'pendingCount' => ScoreChangeRequest::where('status', ScoreChangeStatus::Pending)->count(),
        ]);
    }
}
