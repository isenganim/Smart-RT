<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;

it('links a correction to the transaction it reverses', function () {
    $original = CashTransaction::factory()->create(['type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);
    $koreksi = CashTransaction::factory()->create([
        'type' => TransactionType::KOREKSI,
        'amount' => -500,
        'reverses_id' => $original->id,
        'reason' => 'Salah input',
    ]);

    expect($koreksi->reverses->is($original))->toBeTrue();
    expect($original->corrections)->toHaveCount(1);
});

it('marks a transaction cancelled', function () {
    $tx = CashTransaction::factory()->create([
        'cancelled_at' => now(),
        'reason' => 'Salah input',
    ]);

    expect($tx->isCancelled())->toBeTrue();
});

it('excludes cancelled rows from the active scope', function () {
    CashTransaction::factory()->create(['cancelled_at' => null]);
    CashTransaction::factory()->create([
        'cancelled_at' => now(),
        'reason' => 'Salah input',
    ]);

    expect(CashTransaction::query()->active()->count())->toBe(1);
});

it('prevents hard deleting cash transactions', function () {
    $tx = CashTransaction::factory()->create();

    expect(fn () => $tx->delete())
        ->toThrow(LogicException::class);
});

it('requires a reason for cancelled rows', function () {
    expect(fn () => CashTransaction::factory()->create(['cancelled_at' => now()]))
        ->toThrow(InvalidArgumentException::class);
});

it('requires a reason for correction rows', function () {
    expect(fn () => CashTransaction::factory()->create(['type' => TransactionType::KOREKSI]))
        ->toThrow(InvalidArgumentException::class);
});
