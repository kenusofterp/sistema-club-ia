<?php

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\EvaluationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionExercise;
use App\Models\CompetitionLevel;
use App\Models\CompetitionStation;
use App\Models\Exercise;
use App\Models\User;
use App\Services\Scoring\AverageOfJudgesStrategy;
use Illuminate\Support\Facades\DB;

/**
 * Competencias con jueceo: datos, niveles/grupos, ejercicios, inicio (valida puestos y congela las
 * plantillas) y cierre.
 */
class CompetitionService
{
    /**
     * @param  array{name: string, date: string, location?: ?string, tournament_id?: ?int}  $data
     * @param  array<int, array{id?: ?int, name: string, groups?: array<int, array{id?: ?int, name: string}>}>  $levels
     * @param  array<int, int>  $exerciseIds
     */
    public function save(?Competition $competition, array $data, array $levels, array $exerciseIds, User $by): Competition
    {
        if ($competition?->isFinished()) {
            throw new BusinessRuleException('La competencia está finalizada.');
        }

        if ($levels === []) {
            throw new BusinessRuleException('La competencia tiene que tener al menos un nivel.');
        }

        $this->assertUniqueNames(array_column($levels, 'name'), 'Hay niveles repetidos.');
        foreach ($levels as $level) {
            $this->assertUniqueNames(array_column($level['groups'] ?? [], 'name'), "El nivel «{$level['name']}» tiene grupos repetidos.");
        }

        if ($exerciseIds === []) {
            throw new BusinessRuleException('Elegí al menos un ejercicio.');
        }

        return DB::transaction(function () use ($competition, $data, $levels, $exerciseIds, $by) {
            $attributes = [
                'name' => trim($data['name']),
                'date' => $data['date'],
                'location' => $data['location'] ?? null,
                'tournament_id' => $data['tournament_id'] ?? null,
            ];

            if ($competition) {
                $competition->update($attributes);
            } else {
                $competition = Competition::create([
                    ...$attributes,
                    'status' => CompetitionStatus::Draft,
                    'scoring_strategy' => AverageOfJudgesStrategy::KEY,
                    'created_by' => $by->id,
                ]);
            }

            $this->syncLevels($competition, $levels);
            $this->syncExercises($competition, array_map('intval', $exerciseIds));

            return $competition->load(['levels.groups', 'exercises.exercise']);
        });
    }

    /**
     * Inicia la competencia: cada puesto con sus 3 jueces, cada ejercicio con criterios y con al menos un
     * puesto. Congela las plantillas (snapshot JSONB + criterios normalizados).
     */
    public function start(Competition $competition): Competition
    {
        if (! $competition->isDraft()) {
            throw new BusinessRuleException('La competencia ya fue iniciada.');
        }

        return DB::transaction(function () use ($competition) {
            Competition::query()->lockForUpdate()->findOrFail($competition->id);

            $stations = $competition->stations()->withCount('judges')->get();
            if ($stations->isEmpty()) {
                throw new BusinessRuleException('Creá al menos un puesto de evaluación antes de iniciar.');
            }

            $incomplete = $stations->reject(fn (CompetitionStation $s) => $s->hasFullPanel());
            if ($incomplete->isNotEmpty()) {
                throw new BusinessRuleException('Cada puesto tiene que tener '.CompetitionStation::JUDGES.' jueces. Incompletos: '.$incomplete->pluck('name')->implode(', ').'.');
            }

            foreach ($competition->exercises()->with('exercise.criteria')->get() as $competitionExercise) {
                if (! $stations->contains('competition_exercise_id', $competitionExercise->id)) {
                    throw new BusinessRuleException("El ejercicio «{$competitionExercise->name()}» no tiene ningún puesto.");
                }
                $this->snapshot($competitionExercise);
            }

            $competition->update(['status' => CompetitionStatus::InProgress, 'started_at' => now()]);

            return $competition;
        });
    }

    /** Finaliza: no puede quedar ninguna pasada pendiente (se publican o se marcan ausentes). */
    public function finish(Competition $competition): Competition
    {
        if (! $competition->isInProgress()) {
            throw new BusinessRuleException('Solo se puede finalizar una competencia en curso.');
        }

        $pending = $competition->evaluations()->where('status', EvaluationStatus::Pending)->count();
        if ($pending > 0) {
            throw new BusinessRuleException("Quedan {$pending} pasadas pendientes: terminá de evaluarlas o marcalas como ausentes.");
        }

        $competition->update(['status' => CompetitionStatus::Finished, 'finished_at' => now()]);

        return $competition;
    }

    /** Copia la plantilla vigente del ejercicio a la competencia (una sola vez). */
    public function snapshot(CompetitionExercise $competitionExercise): void
    {
        if ($competitionExercise->isSnapshotted()) {
            return;
        }

        $exercise = $competitionExercise->exercise()->with('criteria')->first();
        if ($exercise->criteria->isEmpty()) {
            throw new BusinessRuleException("El ejercicio «{$exercise->name}» no tiene criterios de evaluación.");
        }

        $competition = $competitionExercise->competition;
        $criteria = $exercise->criteria->values()->map(fn ($c, $order) => [
            'key' => $c->id,
            'name' => $c->name,
            'type' => $c->type->value,
            'min' => (string) $c->min,
            'max' => (string) $c->max,
            'decimals' => $c->decimals,
            'weight' => (string) $c->weight,
            'order' => $order,
        ]);

        $competitionExercise->update([
            'template_snapshot' => [
                'exercise' => ['id' => $exercise->id, 'name' => $exercise->name],
                'precision' => AverageOfJudgesStrategy::scale(),
                'strategy' => $competition->scoring_strategy,
                'criteria' => $criteria->all(),
            ],
            'snapshotted_at' => now(),
        ]);

        foreach ($criteria as $criterion) {
            $competitionExercise->criteria()->create([
                'source_criterion_id' => $criterion['key'],
                'name' => $criterion['name'],
                'type' => $criterion['type'],
                'min' => $criterion['min'],
                'max' => $criterion['max'],
                'decimals' => $criterion['decimals'],
                'weight' => $criterion['weight'],
                'sort_order' => $criterion['order'],
            ]);
        }
    }

    public function delete(Competition $competition): void
    {
        if (! $competition->isDraft()) {
            throw new BusinessRuleException('Solo se puede eliminar una competencia en borrador.');
        }

        $competition->delete();
    }

    /** @param array<int, array{id?: ?int, name: string, groups?: array<int, array{id?: ?int, name: string}>}> $levels */
    private function syncLevels(Competition $competition, array $levels): void
    {
        $keepLevels = [];

        foreach (array_values($levels) as $order => $data) {
            $level = ! empty($data['id']) ? $competition->levels()->find($data['id']) : null;
            $level ? $level->update(['name' => trim($data['name']), 'sort_order' => $order])
                : $level = $competition->levels()->create(['name' => trim($data['name']), 'sort_order' => $order]);
            $keepLevels[] = $level->id;

            $keepGroups = [];
            foreach (array_values($data['groups'] ?? []) as $groupOrder => $groupData) {
                $group = ! empty($groupData['id']) ? $level->groups()->find($groupData['id']) : null;
                $group ? $group->update(['name' => trim($groupData['name']), 'sort_order' => $groupOrder])
                    : $group = $level->groups()->create(['name' => trim($groupData['name']), 'sort_order' => $groupOrder]);
                $keepGroups[] = $group->id;
            }

            $removedGroups = $level->groups()->whereNotIn('id', $keepGroups)->pluck('id');
            if ($removedGroups->isNotEmpty() && CompetitionEntry::whereIn('competition_group_id', $removedGroups)->exists()) {
                throw new BusinessRuleException("No se puede quitar un grupo de «{$level->name}» que tiene participantes inscriptas.");
            }
            $level->groups()->whereIn('id', $removedGroups)->delete();
        }

        $removed = $competition->levels()->whereNotIn('id', $keepLevels)->get();
        foreach ($removed as $level) {
            /** @var CompetitionLevel $level */
            if (CompetitionEntry::where('competition_level_id', $level->id)->exists()) {
                throw new BusinessRuleException("No se puede quitar el nivel «{$level->name}»: tiene participantes inscriptas.");
            }
            $level->delete();
        }
    }

    /** @param array<int, int> $exerciseIds */
    private function syncExercises(Competition $competition, array $exerciseIds): void
    {
        $valid = Exercise::whereIn('id', $exerciseIds)->pluck('id')->all();
        if (count($valid) !== count(array_unique($exerciseIds))) {
            throw new BusinessRuleException('Hay ejercicios que no existen.');
        }

        foreach (array_values($exerciseIds) as $order => $exerciseId) {
            $competitionExercise = $competition->exercises()->firstOrCreate(['exercise_id' => $exerciseId], ['sort_order' => $order]);
            $competitionExercise->update(['sort_order' => $order]);

            // Ejercicio agregado con la competencia en curso: se congela su plantilla en ese momento.
            if ($competition->isInProgress()) {
                $this->snapshot($competitionExercise);
            }
        }

        foreach ($competition->exercises()->whereNotIn('exercise_id', $exerciseIds)->get() as $removed) {
            if ($removed->stations()->exists()) {
                throw new BusinessRuleException("No se puede quitar «{$removed->name()}»: tiene puestos. Borrá primero sus puestos.");
            }
            $removed->delete();
        }
    }

    /** @param array<int, string> $names */
    private function assertUniqueNames(array $names, string $message): void
    {
        $normalized = array_map(fn ($n) => mb_strtolower(trim((string) $n)), $names);
        if (count($normalized) !== count(array_unique($normalized))) {
            throw new BusinessRuleException($message);
        }
    }
}
