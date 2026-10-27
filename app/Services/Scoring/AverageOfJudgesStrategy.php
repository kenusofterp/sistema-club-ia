<?php

namespace App\Services\Scoring;

use App\Enums\CriterionType;
use App\Models\CompetitionCriterion;
use Illuminate\Support\Collection;

/**
 * Regla «promedio»: el total de cada juez es la suma de sus criterios (puntaje suma valor × peso,
 * deducción resta valor × peso, sí/no suma el peso si es «sí»), con piso en 0; el puntaje del ejercicio
 * es el promedio de los totales de los jueces, truncado a la escala fija (8,4666… → 8,466).
 */
class AverageOfJudgesStrategy implements ScoringStrategy
{
    public const KEY = 'promedio';

    /** Escala intermedia: los productos valor × peso se suman sin perder decimales antes de truncar. */
    private const WORK_SCALE = 6;

    public function judgeTotal(array $values, Collection $criteria): string
    {
        $sum = '0';

        foreach ($criteria as $criterion) {
            /** @var CompetitionCriterion $criterion */
            $value = (string) ($values[$criterion->id] ?? '0');
            $weight = (string) $criterion->weight;

            $contribution = match ($criterion->type) {
                CriterionType::Score => bcmul($value, $weight, self::WORK_SCALE),
                CriterionType::Deduction => bcmul('-1', bcmul($value, $weight, self::WORK_SCALE), self::WORK_SCALE),
                CriterionType::YesNo => bccomp($value, '1', 0) === 0 ? $weight : '0',
            };

            $sum = bcadd($sum, $contribution, self::WORK_SCALE);
        }

        if (bccomp($sum, '0', self::WORK_SCALE) < 0) {
            $sum = '0';
        }

        // bcadd con escala menor trunca (no redondea).
        return bcadd($sum, '0', self::scale());
    }

    public function evaluationScore(array $totals): string
    {
        if ($totals === []) {
            return bcadd('0', '0', self::scale());
        }

        $sum = array_reduce($totals, fn (string $carry, $total) => bcadd($carry, (string) $total, self::WORK_SCALE), '0');

        // bcdiv trunca a la escala indicada.
        return bcdiv($sum, (string) count($totals), self::scale());
    }

    public static function scale(): int
    {
        return (int) config('club.competition_score_scale', 3);
    }
}
