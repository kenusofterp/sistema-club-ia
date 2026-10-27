<?php

namespace App\Services;

use App\Enums\CriterionType;
use App\Exceptions\BusinessRuleException;
use App\Models\CompetitionExercise;
use App\Models\Exercise;
use Illuminate\Support\Facades\DB;

/**
 * Ejercicios de competencia y su plantilla de criterios. Editar la plantilla no afecta a las competencias
 * ya iniciadas: cada una usa la copia (snapshot) que se guardó al iniciarla.
 */
class ExerciseService
{
    /**
     * @param  array{name: string, description?: ?string, is_active?: bool}  $data
     * @param  array<int, array{id?: ?int, name: string, type: string, min: string, max: string, decimals: int|string, weight: string}>  $criteria
     */
    public function save(?Exercise $exercise, array $data, array $criteria): Exercise
    {
        if ($criteria === []) {
            throw new BusinessRuleException('El ejercicio tiene que tener al menos un criterio de evaluación.');
        }

        $names = array_map(fn ($c) => mb_strtolower(trim($c['name'])), $criteria);
        if (count($names) !== count(array_unique($names))) {
            throw new BusinessRuleException('Hay criterios con el mismo nombre.');
        }

        foreach ($criteria as $criterion) {
            $this->validateCriterion($criterion);
        }

        return DB::transaction(function () use ($exercise, $data, $criteria) {
            $attributes = [
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            $exercise ? $exercise->update($attributes) : $exercise = Exercise::create([...$attributes, 'sort_order' => (int) Exercise::max('sort_order') + 1]);

            $keep = [];
            foreach (array_values($criteria) as $order => $criterion) {
                $values = [
                    'name' => trim($criterion['name']),
                    'type' => $criterion['type'],
                    'min' => $criterion['type'] === CriterionType::YesNo->value ? '0' : $criterion['min'],
                    'max' => $criterion['type'] === CriterionType::YesNo->value ? '1' : $criterion['max'],
                    'decimals' => $criterion['type'] === CriterionType::YesNo->value ? 0 : (int) $criterion['decimals'],
                    'weight' => $criterion['weight'],
                    'sort_order' => $order,
                ];

                $record = ! empty($criterion['id']) ? $exercise->criteria()->find($criterion['id']) : null;
                $record ? $record->update($values) : $record = $exercise->criteria()->create($values);
                $keep[] = $record->id;
            }

            $exercise->criteria()->whereNotIn('id', $keep)->delete();

            return $exercise->load('criteria');
        });
    }

    /** Se puede borrar si ninguna competencia lo usa; si no, se desactiva. */
    public function delete(Exercise $exercise): void
    {
        if (CompetitionExercise::where('exercise_id', $exercise->id)->exists()) {
            throw new BusinessRuleException('El ejercicio se usa en competencias: desactivalo en lugar de borrarlo.');
        }

        $exercise->delete();
    }

    /** @param array{name: string, type: string, min: string, max: string, decimals: int|string, weight: string} $criterion */
    private function validateCriterion(array $criterion): void
    {
        $name = trim($criterion['name']);
        $type = CriterionType::tryFrom($criterion['type']);

        if (! $type) {
            throw new BusinessRuleException("El criterio «{$name}» tiene un tipo inválido.");
        }

        if (bccomp((string) $criterion['weight'], '0', 3) <= 0) {
            throw new BusinessRuleException("El peso del criterio «{$name}» tiene que ser mayor a 0.");
        }

        if ($type === CriterionType::YesNo) {
            return;
        }

        if (bccomp((string) $criterion['min'], (string) $criterion['max'], 3) > 0) {
            throw new BusinessRuleException("En «{$name}» el mínimo no puede ser mayor que el máximo.");
        }

        if ((int) $criterion['decimals'] < 0 || (int) $criterion['decimals'] > 3) {
            throw new BusinessRuleException("En «{$name}» los decimales van de 0 a 3.");
        }
    }
}
