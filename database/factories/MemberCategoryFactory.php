<?php

namespace Database\Factories;

use App\Models\MemberCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberCategory> */
class MemberCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Categoría '.fake()->unique()->word(),
            'monthly_fee' => 10000,
            'admission_fee' => 0,
            'min_age' => null,
            'max_age' => null,
            'is_active' => true,
        ];
    }
}
