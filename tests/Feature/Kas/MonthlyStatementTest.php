<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Services\KasReport;
use App\Support\Setting;

beforeEach(function () {
    $this->report = app(KasReport::class);
});

it('summarizes income, expenses, and closing balance for a month', function () {
    CashTransaction::factory()->create(['date' => '2026-06-03', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);
    CashTransaction::factory()->create(['date' => '2026-06-10', 'type' => TransactionType::DENDA, 'amount' => 5000]);
    CashTransaction::factory()->create(['date' => '2026-06-15', 'type' => TransactionType::PENGELUARAN, 'amount' => -3000, 'category' => 'Kebersihan', 'status' => 'keluar', 'source' => 'manual', 'reason' => 'Sapu']);

    $statement = $this->report->monthlyStatement(2026, 6);

    expect($statement['income']['iuran'])->toBe(5000)
        ->and($statement['income']['denda'])->toBe(5000)
        ->and($statement['income']['total_in'])->toBe(10000)
        ->and($statement['total_out'])->toBe(3000)
        ->and($statement['net'])->toBe(7000)
        ->and($statement['opening_balance'])->toBe(0)
        ->and($statement['closing_balance'])->toBe(7000);
});

it('groups expenses by category', function () {
    CashTransaction::factory()->create(['date' => '2026-06-02', 'type' => TransactionType::PENGELUARAN, 'amount' => -2000, 'category' => 'Kebersihan', 'status' => 'keluar', 'source' => 'manual', 'reason' => 'A']);
    CashTransaction::factory()->create(['date' => '2026-06-04', 'type' => TransactionType::PENGELUARAN, 'amount' => -5000, 'category' => 'Kebersihan', 'status' => 'keluar', 'source' => 'manual', 'reason' => 'B']);
    CashTransaction::factory()->create(['date' => '2026-06-06', 'type' => TransactionType::PENGELUARAN, 'amount' => -1000, 'category' => null, 'status' => 'keluar', 'source' => 'manual', 'reason' => 'C']);

    $statement = $this->report->monthlyStatement(2026, 6);

    $byCategory = $statement['expenses']->keyBy('category');

    expect($statement['expenses'])->toHaveCount(2)
        ->and($byCategory['Kebersihan']['amount'])->toBe(7000)
        ->and($byCategory['Lainnya']['amount'])->toBe(1000)
        // sorted by amount desc
        ->and($statement['expenses']->first()['category'])->toBe('Kebersihan');
});

it('excludes cancelled transactions from the statement', function () {
    CashTransaction::factory()->create(['date' => '2026-06-03', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);
    CashTransaction::factory()->create(['date' => '2026-06-05', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000, 'cancelled_at' => now(), 'reason' => 'Salah input']);

    $statement = $this->report->monthlyStatement(2026, 6);

    expect($statement['income']['iuran'])->toBe(5000);
});

it('carries the configured opening balance into the month', function () {
    Setting::set('kas_opening_balance', '100000');

    CashTransaction::factory()->create(['date' => '2026-06-10', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);

    $statement = $this->report->monthlyStatement(2026, 6);

    expect($statement['opening_balance'])->toBe(100000)
        ->and($statement['closing_balance'])->toBe(105000);
});

it('carries prior-month transactions into the opening balance', function () {
    CashTransaction::factory()->create(['date' => '2026-05-20', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 8000]);
    CashTransaction::factory()->create(['date' => '2026-06-10', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);

    $statement = $this->report->monthlyStatement(2026, 6);

    expect($statement['opening_balance'])->toBe(8000)
        ->and($statement['closing_balance'])->toBe(13000);
});

it('respects the opening date when computing carryover', function () {
    Setting::set('kas_opening_balance', '50000');
    Setting::set('kas_opening_date', '2026-06-01');

    // Before the opening date — must be ignored.
    CashTransaction::factory()->create(['date' => '2026-05-20', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 9999]);
    CashTransaction::factory()->create(['date' => '2026-06-10', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);

    $statement = $this->report->monthlyStatement(2026, 6);

    expect($statement['opening_balance'])->toBe(50000)
        ->and($statement['closing_balance'])->toBe(55000);
});
