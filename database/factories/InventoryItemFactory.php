<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Kursi Lipat', 'Tenda', 'Sound System', 'Genset', 'Meja Panjang']).' '.fake()->numberBetween(1, 20),
            'condition' => ItemCondition::BAIK,
            'status' => ItemStatus::TERSEDIA,
            'location' => 'Sekretariat RT',
            'holder' => null,
            'notes' => null,
        ];
    }

    public function onLoan(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::DIPINJAM,
            'holder' => fake()->name(),
        ]);
    }
}
