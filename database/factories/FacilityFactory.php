<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Facility> */
class FacilityFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Cancha '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'hourly_rate' => 10000,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'slot_minutes' => 60,
            'max_slots_per_booking' => 2,
            'is_bookable' => true,
            'is_active' => true,
        ];
    }
}
