<?php

namespace App\Livewire\Admin\Competitions;

use App\Enums\EvaluationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Competition;
use App\Services\CompetitionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Tablero de una competencia: estado, inicio/cierre y pestañas (puestos, participantes, colas, pantallas). */
#[Layout('layouts.admin')]
class Show extends Component
{
    use InteractsWithUi;

    public Competition $competition;

    #[Url(as: 'ver')]
    public string $tab = 'puestos';

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
    }

    public function start(CompetitionService $service): void
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->start($this->competition), 'Competencia iniciada: los jueces ya pueden cargar notas.')) {
            $this->competition->refresh();
            $this->tab = 'colas';
        }
    }

    public function finish(CompetitionService $service): void
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->finish($this->competition), 'Competencia finalizada.')) {
            $this->competition->refresh();
        }
    }

    public function delete(CompetitionService $service)
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->delete($this->competition))) {
            session()->flash('success', 'Competencia eliminada.');

            return $this->redirectRoute('admin.competitions.index', navigate: true);
        }
    }

    #[On('competition-changed')]
    public function refreshCompetition(): void
    {
        $this->competition->refresh();
    }

    public function render()
    {
        $evaluations = $this->competition->evaluations()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.admin.competitions.show', [
            'stats' => [
                'entries' => $this->competition->entries()->count(),
                'stations' => $this->competition->stations()->count(),
                'published' => (int) ($evaluations[EvaluationStatus::Published->value] ?? 0),
                'pending' => (int) ($evaluations[EvaluationStatus::Pending->value] ?? 0),
            ],
        ])->title($this->competition->name);
    }
}
