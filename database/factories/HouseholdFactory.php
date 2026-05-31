<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'house_number' => 'No. '.fake()->unique()->numberBetween(1, 200),
            'address' => fake()->streetAddress(),
            'head_name' => fake()->name(),
            'qr_token' => (string) Str::uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
