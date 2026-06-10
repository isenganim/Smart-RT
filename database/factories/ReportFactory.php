<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return ['phone' => '8'.fake()->unique()->numerify('##########'), 'category' => 'Lainnya', 'description' => fake()->sentence(), 'status' => ReportStatus::BARU, 'notes' => null];
    }
}
