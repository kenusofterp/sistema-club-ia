<?php

namespace App\Services\Scoring;

use App\Models\CompetitionCriterion;
use Illuminate\Support\Collection;

/**
 * Regla de cálculo de una competencia (patrón Strategy). Todo se calcula con bcmath y se devuelve
 * como string con la escala de config('club.competition_score_scale').
 */
interface ScoringStrategy
{
    /**
     * Total de la planilla de un juez.
     *
     * @param  array<int, string>  $values  criterio => valor (ya validado)
     * @param  Collection<int, CompetitionCriterion>  $criteria
     */
    public function judgeTotal(array $values, Collection $criteria): string;

    /**
     * Puntaje del ejercicio a partir de los totales de los jueces.
     *
     * @param  array<int, string>  $totals
     */
    public function evaluationScore(array $totals): string;
}
