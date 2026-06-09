<?php

namespace Database\Factories;

use App\Enums\VoteStatus;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return ['question' => fake()->sentence().'?', 'status' => VoteStatus::DRAFT, 'starts_at' => null, 'ends_at' => null];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => VoteStatus::AKTIF, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
    }
}
