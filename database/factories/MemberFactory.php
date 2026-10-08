<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['F', 'M']);

        return [
            'member_number' => null,
            'first_name' => fake()->firstName($gender === 'F' ? 'female' : 'male'),
            'last_name' => fake()->lastName(),
            'document_type' => 'DNI',
            'document_number' => (string) fake()->unique()->numberBetween(10000000, 60000000),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-19 years')->format('Y-m-d'),
            'gender' => $gender,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('11########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'member_category_id' => MemberCategory::factory(),
            'status' => MemberStatus::Active,
            'admission_date' => fake()->dateTimeBetween('-5 years', '-3 months')->format('Y-m-d'),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => MemberStatus::Pending, 'admission_date' => null]);
    }

    public function withNumber(): static
    {
        return $this->sequence(fn ($sequence) => ['member_number' => str_pad((string) ($sequence->index + 1), 5, '0', STR_PAD_LEFT)]);
    }
}
