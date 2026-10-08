<?php

namespace Database\Factories;

use App\Enums\PlanAccessType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Plan '.fake()->unique()->word(),
            'price' => 20000,
            'duration_unit' => 'meses',
            'duration_value' => 1,
            'access_type' => PlanAccessType::Free,
            'visit_limit' => null,
            'visit_period' => 'mes',
            'access_windows' => null,
            'is_public' => true,
            'is_active' => true,
        ];
    }
}
