<?php

namespace Database\Factories;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\LetterRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LetterRequestFactory extends Factory
{
    protected $model = LetterRequest::class;

    public function definition(): array
    {
        return ['phone' => '8'.fake()->unique()->numerify('##########'), 'type' => LetterType::DOMISILI, 'purpose' => fake()->sentence(), 'status' => LetterStatus::DIAJUKAN, 'notes' => null];
    }
}
