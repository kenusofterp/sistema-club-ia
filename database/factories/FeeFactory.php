<?php

namespace Database\Factories;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Models\Fee;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fee> */
class FeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'type' => FeeType::Other,
            'concept' => 'Cargo de prueba',
            'amount' => 1000,
            'surcharge' => 0,
            'paid_amount' => 0,
            'due_date' => today()->addDays(10),
            'status' => FeeStatus::Pending,
        ];
    }

    public function overdue(): static
    {
        return $this->state(['status' => FeeStatus::Overdue, 'due_date' => today()->subDays(15)]);
    }
}
