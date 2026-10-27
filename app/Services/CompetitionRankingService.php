<?php

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\Competition;
use App\Models\CompetitionExercise;
use App\Models\CompetitionLevel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rankings por nivel/grupo, por ejercicio y all-around (suma de ejercicios). Sin desempate: los puntajes
 * iguales (numeric con 3 decimales) comparten el puesto y el siguiente salta — RANK() de PostgreSQL (1, 1, 3).
 * Con $limit se devuelven los puestos <= $limit, incluidas todas las empatadas en el último.
 */
class CompetitionRankingService
{
    /** @return Collection<int, object{rank: int, entry_id: int, bib_number: string, first_name: string, last_name: string, institution: ?string, score: string}> */
    public function byExercise(Competition $competition, CompetitionExercise $exercise, int $levelId, ?int $groupId = null, ?int $limit = null): Collection
    {
        $ranked = $this->entriesQuery($competition, $levelId, $groupId)
            ->join('evaluations as ev', 'ev.competition_entry_id', '=', 'en.id')
            ->where('ev.competition_exercise_id', $exercise->id)
            ->where('ev.status', EvaluationStatus::Published->value)
            ->select(['en.id as entry_id', 'en.bib_number', 'c.first_name', 'c.last_name', 'c.institution', 'ev.score'])
            ->selectRaw('RANK() OVER (ORDER BY ev.score DESC) AS rank');

        return $this->finish($ranked, $limit);
    }

    /**
     * All-around: suma de los ejercicios publicados. Si le falta alguno de los asignados queda «incompleta»
     * pero rankea igual.
     *
     * @return Collection<int, object{rank: int, entry_id: int, bib_number: string, first_name: string, last_name: string, institution: ?string, score: string, done: int, expected: int, incomplete: bool}>
     */
    public function allAround(Competition $competition, int $levelId, ?int $groupId = null, ?int $limit = null): Collection
    {
        $published = EvaluationStatus::Published->value;

        $ranked = $this->entriesQuery($competition, $levelId, $groupId)
            ->join('evaluations as ev', 'ev.competition_entry_id', '=', 'en.id')
            ->groupBy('en.id', 'en.bib_number', 'c.first_name', 'c.last_name', 'c.institution')
            ->havingRaw('COUNT(*) FILTER (WHERE ev.status = ?) > 0', [$published])
            ->select(['en.id as entry_id', 'en.bib_number', 'c.first_name', 'c.last_name', 'c.institution'])
            ->selectRaw('SUM(ev.score) FILTER (WHERE ev.status = ?) AS score', [$published])
            ->selectRaw('COUNT(*) FILTER (WHERE ev.status = ?) AS done', [$published])
            ->selectRaw('COUNT(*) AS expected')
            ->selectRaw('RANK() OVER (ORDER BY SUM(ev.score) FILTER (WHERE ev.status = ?) DESC) AS rank', [$published]);

        return $this->finish($ranked, $limit)->each(function (object $row) {
            $row->done = (int) $row->done;
            $row->expected = (int) $row->expected;
            $row->incomplete = $row->done < $row->expected;
        });
    }

    /**
     * Planilla completa de una categoría: all-around con el puntaje y el puesto de cada ejercicio
     * (exercises[competition_exercise_id] = ['score' => …, 'rank' => …] o null si no tiene nota).
     */
    public function table(Competition $competition, int $levelId, ?int $groupId = null): Collection
    {
        $byExercise = $competition->exercises->mapWithKeys(fn (CompetitionExercise $exercise) => [
            $exercise->id => $this->byExercise($competition, $exercise, $levelId, $groupId)->keyBy('entry_id'),
        ]);

        return $this->allAround($competition, $levelId, $groupId)->each(function (object $row) use ($byExercise) {
            $row->exercises = $byExercise->map(function (Collection $ranking) use ($row) {
                $result = $ranking->get($row->entry_id);

                return $result ? ['score' => $result->score, 'rank' => $result->rank] : null;
            })->all();
        });
    }

    /**
     * Categorías en las que se rankea: cada grupo del nivel o, si el nivel no tiene grupos, el nivel.
     *
     * @return Collection<int, array{key: string, level_id: int, group_id: ?int, label: string}>
     */
    public function categories(Competition $competition): Collection
    {
        return $competition->levels()->with('groups')->get()->flatMap(function (CompetitionLevel $level) {
            if ($level->groups->isEmpty()) {
                return [['key' => "{$level->id}", 'level_id' => $level->id, 'group_id' => null, 'label' => $level->name]];
            }

            return $level->groups->map(fn ($group) => [
                'key' => "{$level->id}-{$group->id}",
                'level_id' => $level->id,
                'group_id' => $group->id,
                'label' => "{$level->name} · {$group->name}",
            ]);
        })->values();
    }

    private function entriesQuery(Competition $competition, int $levelId, ?int $groupId): Builder
    {
        return DB::table('competition_entries as en')
            ->join('competitors as c', 'c.id', '=', 'en.competitor_id')
            ->where('en.competition_id', $competition->id)
            ->where('en.competition_level_id', $levelId)
            ->when($groupId, fn ($q) => $q->where('en.competition_group_id', $groupId));
    }

    private function finish(Builder $ranked, ?int $limit): Collection
    {
        return DB::query()
            ->fromSub($ranked, 'r')
            ->when($limit, fn ($q) => $q->where('rank', '<=', $limit))
            ->orderBy('rank')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->each(function (object $row) {
                $row->rank = (int) $row->rank;
                $row->score = (string) $row->score;
            });
    }
}
