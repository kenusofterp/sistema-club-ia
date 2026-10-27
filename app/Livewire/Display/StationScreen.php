<?php

namespace App\Livewire\Display;

use App\Enums\EvaluationStatus;
use App\Livewire\Display\Concerns\ResolvesCompetition;
use App\Models\CompetitionStation;
use App\Models\Evaluation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla pública de un puesto (TV): participante actual, ✓ de cada juez y, apenas se publica, las 3
 * notas y el puntaje. El último publicado queda en pantalla unos segundos antes de pasar a la siguiente.
 */
#[Layout('layouts.display')]
class StationScreen extends Component
{
    use ResolvesCompetition;

    /** Segundos que se muestra el puntaje recién publicado antes de pasar a la siguiente. */
    private const SHOW_RESULT_SECONDS = 20;

    #[Locked]
    public string $token;

    public function mount(string $token): void
    {
        $station = CompetitionStation::where('display_token', $token)->firstOrFail();
        $this->token = $token;
        $this->competitionId = $station->competition_id;
        $this->useOrganizationOf($station->competition_id);
    }

    public function getListeners(): array
    {
        return collect(['JudgeConfirmed', 'EvaluationPublished', 'StationAdvanced', 'ScoreUpdated'])
            ->mapWithKeys(fn ($event) => ["echo:display.station.{$this->token},{$event}" => '$refresh'])
            ->all();
    }

    public function render()
    {
        $station = CompetitionStation::where('display_token', $this->token)
            ->with(['competition', 'competitionExercise', 'level', 'group'])
            ->firstOrFail();

        $current = $station->currentEvaluation()?->load(['entry.competitor', 'entry.level', 'entry.group', 'sheets']);

        // Último publicado (recién): se muestra con sus notas mientras no pasaron unos segundos.
        $last = $station->evaluations()
            ->where('status', EvaluationStatus::Published)
            ->reorder()->latest('published_at')
            ->with(['entry.competitor', 'entry.level', 'entry.group', 'sheets'])
            ->first();
        $showLast = $last && ($last->published_at->diffInSeconds(now()) < self::SHOW_RESULT_SECONDS || ! $current);

        return view('livewire.display.station-screen', [
            'station' => $station,
            'current' => $current,
            'last' => $showLast ? $last : null,
            'next' => $station->evaluations()->where('status', EvaluationStatus::Pending)->with('entry.competitor')->skip(1)->take(3)->get(),
        ])->title($station->name.' · '.$station->competition->name);
    }

    /** @return array<int, string|null> asiento => total (solo de las planillas confirmadas) */
    public static function totals(Evaluation $evaluation): array
    {
        return collect(range(1, CompetitionStation::JUDGES))
            ->mapWithKeys(fn ($seat) => [$seat => $evaluation->sheets->firstWhere('seat', $seat)?->total])
            ->all();
    }
}
