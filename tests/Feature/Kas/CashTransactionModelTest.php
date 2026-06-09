<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;

it('casts type to enum and date to a date', function () {
    $tx = CashTransaction::factory()->create([
        'type' => TransactionType::IURAN_HARIAN,
        'date' => '2026-06-01',
        'amount' => 500,
    ]);

    expect($tx->fresh()->type)->toBe(TransactionType::IURAN_HARIAN);
    expect($tx->date->toDateString())->toBe('2026-06-01');
    expect($tx->amount)->toBe(500);
});

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $tx = CashTransaction::factory()->for($household)->create();

    expect($tx->household->is($household))->toBeTrue();
});

it('scopes iuran harian transactions', function () {
    CashTransaction::factory()->create(['type' => TransactionType::IURAN_HARIAN]);
    CashTransaction::factory()->create(['type' => TransactionType::DENDA]);

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});
