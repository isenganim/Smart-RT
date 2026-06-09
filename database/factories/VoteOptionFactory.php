<?php

namespace Database\Factories;

use App\Models\Vote;
use App\Models\VoteOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteOptionFactory extends Factory
{
    protected $model = VoteOption::class;

    public function definition(): array
    {
        return ['vote_id' => Vote::factory(), 'label' => fake()->words(2, true)];
    }
}
