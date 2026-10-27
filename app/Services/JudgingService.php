<?php

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CriterionType;
use App\Enums\EvaluationStatus;
use App\Events\EvaluationPublished;
use App\Events\JudgeConfirmed;
use App\Events\StationAdvanced;
use App\Exceptions\BusinessRuleException;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionStation;
use App\Models\Evaluation;
use App\Models\JudgeSheet;
use App\Models\StationJudge;
use App\Models\User;
use App\Services\Scoring\AverageOfJudgesStrategy;
use App\Services\Scoring\ScoringStrategyResolver;
use App\Support\Realtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carga de notas de los jueces. Cada juez confirma su planilla una sola vez (los reintentos con los mismos
 * valores son idempotentes); cuando confirman los 3, se calcula y publica el puntaje y el puesto avanza.
 */
class JudgingService
{
    public function __construct(private ScoringStrategyResolver $strategies) {}

    /** Puesto del juez en la competencia en curso de la entidad actual (la iniciada más recientemente). */
    public function assignmentFor(User $user): ?StationJudge
    {
        return StationJudge::query()
            ->where('user_id', $user->id)
            ->whereHas('station.competition', fn ($q) => $q->where('status', CompetitionStatus::InProgress))
            ->with(['station.competition', 'station.competitionExercise'])
            ->get()
            ->sortByDesc(fn (StationJudge $j) => $j->station->competition->started_at)
            ->first();
    }

    /**
     * Confirma la nota del juez para una pasada.
     *
     * @param  array<int|string, mixed>  $values  criterio => valor
     */
    public function confirm(Evaluation $evaluation, User $judge, array $values): JudgeSheet
    {
        return DB::transaction(function () use ($evaluation, $judge, $values) {
            /** @var Evaluation $evaluation */
            $evaluation = Evaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            $competition = $evaluation->competition;

            if (! $competition->isInProgress()) {
                throw new BusinessRuleException('La competencia no está en curso.');
            }

            $seat = StationJudge::where('competition_station_id', $evaluation->competition_station_id)->where('user_id', $judge->id)->value('seat');
            if (! $seat) {
                throw new BusinessRuleException('No sos juez de este puesto.');
            }

            $criteria = $evaluation->competitionExercise->criteria;
            $normalized = $this->normalize($criteria, $values);

            $existing = $evaluation->sheets()->where('seat', $seat)->with('scores')->first();
            if ($existing?->isConfirmed()) {
                // Reintento por conexión inestable: si es lo mismo, se responde OK sin cambiar nada.
                if ($existing->values() == $normalized) {
                    return $existing;
                }

                throw new BusinessRuleException('Tu nota ya está confirmada. Para cambiarla, pedí una modificación.');
            }

            if ($evaluation->status !== EvaluationStatus::Pending) {
                throw new BusinessRuleException($evaluation->status === EvaluationStatus::Absent
                    ? 'La participante fue marcada como ausente.'
                    : 'La nota de esta participante ya fue publicada.');
            }

            $strategy = $this->strategies->for($competition);
            $sheet = $evaluation->sheets()->create([
                'user_id' => $judge->id,
                'seat' => $seat,
                'total' => $strategy->judgeTotal($normalized, $criteria),
                'confirmed_at' => now(),
            ]);

            foreach ($normalized as $criterionId => $value) {
                $sheet->scores()->create(['competition_criterion_id' => $criterionId, 'value' => $value]);
            }

            Realtime::send(new JudgeConfirmed($evaluation, $seat));

            $confirmed = $evaluation->sheets()->whereNotNull('confirmed_at')->get();
            if ($confirmed->count() === CompetitionStation::JUDGES) {
                $this->publish($evaluation, $confirmed->pluck('total')->map(fn ($t) => (string) $t)->all());
            }

            return $sheet;
        });
    }

    /** Recalcula el puntaje publicado a partir de las planillas (después de una modificación aprobada). */
    public function recalculate(Evaluation $evaluation): ?string
    {
        if (! $evaluation->isPublished()) {
            return null;
        }

        $totals = $evaluation->sheets()->whereNotNull('confirmed_at')->pluck('total')->map(fn ($t) => (string) $t)->all();
        $score = $this->strategies->for($evaluation->competition)->evaluationScore($totals);
        $evaluation->update(['score' => $score]);

        return $score;
    }

    /**
     * Valida los valores contra los criterios congelados (tipo, rango y decimales) y los normaliza a la
     * escala fija. Sí/no: 1 o 0.
     *
     * @param  Collection<int, CompetitionCriterion>  $criteria
     * @param  array<int|string, mixed>  $values
     * @return array<int, string> criterio => valor
     */
    public function normalize(Collection $criteria, array $values): array
    {
        $scale = AverageOfJudgesStrategy::scale();
        $normalized = [];

        foreach ($criteria as $criterion) {
            $raw = $values[$criterion->id] ?? null;

            if ($criterion->type === CriterionType::YesNo) {
                $normalized[$criterion->id] = bcadd(in_array($raw, [true, 1, '1', 'si', 'sí', 'true'], true) ? '1' : '0', '0', $scale);

                continue;
            }

            $value = str_replace(',', '.', trim((string) $raw));
            if ($value === '') {
                throw new BusinessRuleException("Completá «{$criterion->name}».");
            }

            if (! preg_match('/^-?\d+(?:\.(\d+))?$/', $value, $match)) {
                throw new BusinessRuleException("«{$criterion->name}»: ingresá un número.");
            }

            $decimals = strlen(rtrim($match[1] ?? '', '0'));
            if ($decimals > $criterion->decimals) {
                throw new BusinessRuleException("«{$criterion->name}» admite ".($criterion->decimals === 0 ? 'solo números enteros' : "hasta {$criterion->decimals} decimales").'.');
            }

            if (bccomp($value, (string) $criterion->min, $scale) < 0 || bccomp($value, (string) $criterion->max, $scale) > 0) {
                throw new BusinessRuleException("«{$criterion->name}» tiene que estar entre ".$this->format($criterion->min).' y '.$this->format($criterion->max).'.');
            }

            $normalized[$criterion->id] = bcadd($value, '0', $scale);
        }

        return $normalized;
    }

    /** @param array<int, string> $totals */
    private function publish(Evaluation $evaluation, array $totals): void
    {
        $evaluation->update([
            'score' => $this->strategies->for($evaluation->competition)->evaluationScore($totals),
            'status' => EvaluationStatus::Published,
            'published_at' => now(),
        ]);

        Realtime::send(new EvaluationPublished($evaluation->load('sheets')));
        Realtime::send(new StationAdvanced($evaluation->station));
    }

    private function format(string $number): string
    {
        return rtrim(rtrim(str_replace('.', ',', $number), '0'), ',');
    }
}
