<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Actividad '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'summary' => fake()->sentence(),
            'monthly_fee' => 5000,
            'capacity' => null,
            'is_public' => true,
            'allows_enrollment' => true,
            'is_active' => true,
        ];
    }
}
