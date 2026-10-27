<?php

namespace App\Livewire\Admin\Judge;

use App\Enums\ScoreChangeStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Evaluation;
use App\Models\JudgeSheet;
use App\Models\ScoreChangeRequest;
use App\Models\StationJudge;
use App\Services\JudgingService;
use App\Services\ScoreChangeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla del juez (celular): participante actual de su puesto, criterios dinámicos del ejercicio y
 * confirmación de su nota. Los valores viven en el navegador (Alpine + localStorage) hasta que el servidor
 * confirma, así un corte de conexión no pierde lo cargado y se reintenta solo.
 */
#[Layout('layouts.judge')]
#[Title('Mi puesto')]
class Station extends Component
{
    use InteractsWithUi;

    public ?int $assignmentId = null;

    // ---- Pedido de modificación ----
    public bool $showChange = false;

    public ?int $changeSheetId = null;

    /** @var array<int, mixed> */
    public array $changeValues = [];

    public string $changeReason = '';

    public function mount(JudgingService $judging): void
    {
        $this->assignmentId = $judging->assignmentFor(auth()->user())?->id;
    }

    /** Echo: se refresca cuando cambia el puesto (lo escucha la vista con el token del puesto). */
    public function getListeners(): array
    {
        $token = $this->assignment()?->station->display_token;

        return $token ? [
            "echo:display.station.{$token},JudgeConfirmed" => '$refresh',
            "echo:display.station.{$token},EvaluationPublished" => '$refresh',
            "echo:display.station.{$token},StationAdvanced" => '$refresh',
            "echo:display.station.{$token},ScoreUpdated" => '$refresh',
        ] : [];
    }

    /**
     * Lo llama Alpine con los valores del formulario. Devuelve el resultado para que el navegador sepa si
     * limpiar lo guardado (ok) o mostrar el error (regla); si falla la red, el navegador reintenta.
     *
     * @param  array<int|string, mixed>  $values
     * @return array{ok: bool, message?: string}
     */
    public function confirm(int $evaluationId, array $values, JudgingService $judging): array
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $this->authorize('create', [JudgeSheet::class, $evaluation]);

        try {
            $judging->confirm($evaluation, auth()->user(), $values);
        } catch (BusinessRuleException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->notify('Nota confirmada.');

        return ['ok' => true];
    }

    public function askChange(int $sheetId): void
    {
        $sheet = JudgeSheet::with('scores')->findOrFail($sheetId);
        $this->authorize('requestChange', $sheet);

        $this->changeSheetId = $sheet->id;
        $this->changeValues = collect($sheet->values())->map(fn ($v) => rtrim(rtrim($v, '0'), '.'))->all();
        $this->changeReason = '';
        $this->resetValidation();
        $this->showChange = true;
    }

    public function requestChange(ScoreChangeService $service): void
    {
        $sheet = JudgeSheet::findOrFail($this->changeSheetId);
        $this->authorize('requestChange', $sheet);
        $this->validate(['changeReason' => 'required|string|min:5|max:500'], [], ['changeReason' => 'motivo']);

        if ($this->attempt(fn () => $service->request($sheet, $this->changeValues, $this->changeReason, auth()->user()), 'Pedido enviado. Queda pendiente hasta que lo autoricen.')) {
            $this->showChange = false;
        }
    }

    private function assignment(): ?StationJudge
    {
        return $this->assignmentId ? StationJudge::with(['station.competition', 'station.competitionExercise'])->find($this->assignmentId) : null;
    }

    public function render()
    {
        $assignment = $this->assignment();

        // El juez pudo haber sido reemplazado o la competencia terminó.
        if ($assignment && ! $assignment->station->competition->isInProgress()) {
            $assignment = null;
        }

        $station = $assignment?->station;
        $current = $station?->currentEvaluation()?->load(['entry.competitor', 'entry.level', 'entry.group', 'sheets']);
        $criteria = $station?->competitionExercise->criteria ?? collect();
        $mySheet = $current?->sheets->firstWhere('user_id', auth()->id());

        return view('livewire.admin.judge.station', [
            'assignment' => $assignment,
            'station' => $station,
            'current' => $current,
            'criteria' => $criteria,
            'mySheet' => $mySheet?->load('scores'),
            'confirmedSeats' => $current?->sheets->pluck('seat')->all() ?? [],
            'recent' => $station ? JudgeSheet::query()
                ->where('user_id', auth()->id())
                ->whereHas('evaluation', fn ($q) => $q->where('competition_station_id', $station->id))
                ->with(['evaluation.entry.competitor', 'scores'])
                ->latest('confirmed_at')
                ->limit(8)
                ->get() : collect(),
            'pendingChanges' => $station ? ScoreChangeRequest::where('status', ScoreChangeStatus::Pending)
                ->whereHas('sheet', fn ($q) => $q->where('user_id', auth()->id()))
                ->pluck('judge_sheet_id')->all() : [],
            'changeCriteria' => $this->changeSheetId ? JudgeSheet::find($this->changeSheetId)?->evaluation->competitionExercise->criteria ?? collect() : collect(),
        ]);
    }
}
