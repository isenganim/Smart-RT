<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;

it('casts condition and status to enums', function () {
    $item = InventoryItem::factory()->create([
        'condition' => ItemCondition::RUSAK_RINGAN,
        'status' => ItemStatus::DIPINJAM,
    ]);

    expect($item->fresh()->condition)->toBe(ItemCondition::RUSAK_RINGAN)
        ->and($item->fresh()->status)->toBe(ItemStatus::DIPINJAM);
});

it('defaults new items to good condition and available status', function () {
    $item = InventoryItem::query()->create(['name' => 'Tenda RT']);

    expect($item->fresh()->condition)->toBe(ItemCondition::BAIK)
        ->and($item->fresh()->status)->toBe(ItemStatus::TERSEDIA);
});

it('scopes available items', function () {
    InventoryItem::factory()->create(['status' => ItemStatus::TERSEDIA]);
    InventoryItem::factory()->create(['status' => ItemStatus::DIPINJAM]);
    InventoryItem::factory()->create(['status' => ItemStatus::TIDAK_AKTIF]);

    expect(InventoryItem::query()->available()->count())->toBe(1);
});

it('reports whether an item is on loan', function () {
    $available = InventoryItem::factory()->create(['status' => ItemStatus::TERSEDIA]);
    $onLoan = InventoryItem::factory()->onLoan()->create();

    expect($available->isOnLoan())->toBeFalse()
        ->and($onLoan->isOnLoan())->toBeTrue();
});
