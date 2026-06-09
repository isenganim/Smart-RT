<?php

namespace Database\Factories;

use App\Models\RondaScanSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaScanSessionFactory extends Factory
{
    protected $model = RondaScanSession::class;

    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'pin' => RondaScanSession::generatePin(),
            'starts_at' => today()->setTime(18, 0),
            'ends_at' => today()->addDay()->setTime(6, 0),
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);
    }
}
