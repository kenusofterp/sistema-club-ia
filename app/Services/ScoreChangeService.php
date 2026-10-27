<?php

namespace App\Services;

use App\Enums\ScoreChangeStatus;
use App\Events\ScoreChangeRequested;
use App\Events\ScoreUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Evaluation;
use App\Models\JudgeSheet;
use App\Models\ScoreChangeRequest;
use App\Models\User;
use App\Services\Scoring\ScoringStrategyResolver;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

/**
 * Modificación de notas confirmadas: se pide con motivo, la aprueba o rechaza otra persona con el permiso
 * notas.autorizar y, si se aprueba, se aplica y se recalcula. La solicitud guarda valores, totales y
 * puntaje anteriores y nuevos: nunca se cambia una nota sin dejar registro.
 */
class ScoreChangeService
{
    public function __construct(private JudgingService $judging, private ScoringStrategyResolver $strategies) {}

    /** @param array<int|string, mixed> $newValues criterio => valor */
    public function request(JudgeSheet $sheet, array $newValues, string $reason, User $by): ScoreChangeRequest
    {
        $evaluation = $sheet->evaluation;

        if (! $evaluation->competition->isInProgress()) {
            throw new BusinessRuleException('Solo se pueden modificar notas con la competencia en curso.');
        }

        if (! $sheet->isConfirmed()) {
            throw new BusinessRuleException('La nota todavía no fue confirmada: el juez puede corregirla antes de confirmar.');
        }

        if (mb_strlen(trim($reason)) < 5) {
            throw new BusinessRuleException('Indicá el motivo de la modificación.');
        }

        $criteria = $evaluation->competitionExercise->criteria;
        $new = $this->judging->normalize($criteria, $newValues);
        $old = $sheet->load('scores')->values();

        if ($new == $old) {
            throw new BusinessRuleException('Los valores nuevos son iguales a los actuales.');
        }

        return DB::transaction(function () use ($sheet, $evaluation, $criteria, $new, $old, $reason, $by) {
            JudgeSheet::query()->lockForUpdate()->findOrFail($sheet->id);

            if (ScoreChangeRequest::where('judge_sheet_id', $sheet->id)->where('status', ScoreChangeStatus::Pending)->exists()) {
                throw new BusinessRuleException('Ya hay un pedido de modificación pendiente para esta nota.');
            }

            $request = ScoreChangeRequest::create([
                'competition_id' => $evaluation->competition_id,
                'evaluation_id' => $evaluation->id,
                'judge_sheet_id' => $sheet->id,
                'requested_by' => $by->id,
                'reason' => trim($reason),
                'old_values' => $old,
                'new_values' => $new,
                'old_total' => (string) $sheet->total,
                'new_total' => $this->strategies->for($evaluation->competition)->judgeTotal($new, $criteria),
                'old_score' => $evaluation->score !== null ? (string) $evaluation->score : null,
                'status' => ScoreChangeStatus::Pending,
            ]);

            Realtime::send(new ScoreChangeRequested($request));

            return $request;
        });
    }

    /** Aplica la modificación y recalcula. Quien aprueba no puede ser quien pidió (salvo super administrador). */
    public function approve(ScoreChangeRequest $request, User $by, ?string $note = null): ScoreChangeRequest
    {
        $this->assertCanResolve($request, $by);

        return DB::transaction(function () use ($request, $by, $note) {
            /** @var ScoreChangeRequest $request */
            $request = ScoreChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            /** @var Evaluation $evaluation */
            $evaluation = Evaluation::query()->lockForUpdate()->findOrFail($request->evaluation_id);
            $sheet = JudgeSheet::query()->lockForUpdate()->with('scores')->findOrFail($request->judge_sheet_id);

            if (! $request->isPending()) {
                throw new BusinessRuleException('El pedido ya fue resuelto.');
            }

            if (! $evaluation->competition->isInProgress()) {
                throw new BusinessRuleException('Solo se pueden modificar notas con la competencia en curso.');
            }

            // La nota cambió desde que se pidió (otra modificación): el pedido quedó desactualizado.
            if ($sheet->values() != $request->old_values) {
                throw new BusinessRuleException('La nota cambió desde que se hizo el pedido. Rechazalo y pedí uno nuevo.');
            }

            $oldScore = $evaluation->score !== null ? (string) $evaluation->score : null;

            foreach ($request->new_values as $criterionId => $value) {
                $sheet->scores->firstWhere('competition_criterion_id', (int) $criterionId)?->update(['value' => $value]);
            }
            $sheet->update(['total' => $request->new_total]);

            $newScore = $this->judging->recalculate($evaluation);

            $request->update([
                'status' => ScoreChangeStatus::Approved,
                'new_score' => $newScore,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            if ($evaluation->isPublished()) {
                Realtime::send(new ScoreUpdated($evaluation, $oldScore, $newScore));
            }

            return $request;
        });
    }

    public function reject(ScoreChangeRequest $request, User $by, ?string $note = null): ScoreChangeRequest
    {
        $this->assertCanResolve($request, $by);

        return DB::transaction(function () use ($request, $by, $note) {
            $request = ScoreChangeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $request->isPending()) {
                throw new BusinessRuleException('El pedido ya fue resuelto.');
            }

            $request->update([
                'status' => ScoreChangeStatus::Rejected,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            return $request;
        });
    }

    private function assertCanResolve(ScoreChangeRequest $request, User $by): void
    {
        if ($by->cannot('resolve', $request)) {
            throw new BusinessRuleException($request->requested_by === $by->id
                ? 'El pedido lo tiene que autorizar otra persona.'
                : 'No tenés permiso para autorizar modificaciones de notas.');
        }
    }
}
