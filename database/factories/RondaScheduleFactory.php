<?php

namespace Database\Factories;

use App\Models\RondaSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaScheduleFactory extends Factory
{
    protected $model = RondaSchedule::class;

    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
