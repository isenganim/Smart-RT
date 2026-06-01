<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaAssignmentFactory extends Factory
{
    protected $model = RondaAssignment::class;

    public function definition(): array
    {
        return [
            'ronda_schedule_id' => RondaSchedule::factory(),
            'resident_id' => Resident::factory()->for(Household::factory()),
            'checked_in_at' => null,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['checked_in_at' => now()]);
    }
}
