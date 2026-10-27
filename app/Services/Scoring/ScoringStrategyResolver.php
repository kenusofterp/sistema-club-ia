<?php

namespace App\Services\Scoring;

use App\Exceptions\BusinessRuleException;
use App\Models\Competition;

/** Devuelve la regla de cálculo de una competencia (queda guardada en competitions.scoring_strategy). */
class ScoringStrategyResolver
{
    /** @var array<string, class-string<ScoringStrategy>> */
    private const STRATEGIES = [
        AverageOfJudgesStrategy::KEY => AverageOfJudgesStrategy::class,
    ];

    public function for(Competition $competition): ScoringStrategy
    {
        $class = self::STRATEGIES[$competition->scoring_strategy] ?? null;

        if (! $class) {
            throw new BusinessRuleException("Regla de cálculo desconocida: {$competition->scoring_strategy}.");
        }

        return app($class);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::STRATEGIES);
    }
}
