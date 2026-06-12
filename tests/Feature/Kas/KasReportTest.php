<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Services\KasReport;

beforeEach(function () {
    $this->report = new KasReport;
});

it('sums active transactions for a single day', function () {
    CashTransaction::factory()->count(3)->create(['date' => today(), 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);
    CashTransaction::factory()->create(['date' => today(), 'type' => TransactionType::DENDA, 'amount' => 5000]);

    $summary = $this->report->daily(today());

    expect($summary['iuran'])->toBe(1500);
    expect($summary['denda'])->toBe(5000);
    expect($summary['total'])->toBe(6500);
});

it('ignores cancelled transactions in totals', function () {
    CashTransaction::factory()->create(['date' => today(), 'amount' => 500]);
    CashTransaction::factory()->create([
        'date' => today(),
        'amount' => 500,
        'cancelled_at' => now(),
        'reason' => 'Salah input',
    ]);

    expect($this->report->daily(today())['total'])->toBe(500);
});

it('sums a date range for monthly totals', function () {
    CashTransaction::factory()->create(['date' => today()->startOfMonth(), 'amount' => 500]);
    CashTransaction::factory()->create(['date' => today()->endOfMonth(), 'amount' => 500]);

    $total = $this->report->rangeTotal(today()->copy()->startOfMonth(), today()->copy()->endOfMonth());

    expect($total)->toBe(1000);
});

it('lists households that have not paid iuran on a date', function () {
    $paid = Household::factory()->create(['is_active' => true]);
    $unpaid = Household::factory()->create(['is_active' => true]);
    CashTransaction::factory()->for($paid)->create(['date' => today(), 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);

    $unpaidList = $this->report->unpaidHouseholds(today());

    expect($unpaidList->pluck('id'))->toContain($unpaid->id);
    expect($unpaidList->pluck('id'))->not->toContain($paid->id);
});

it('lists missing check-ins for a date', function () {
    $schedule = RondaSchedule::factory()->create(['date' => today()->subDay()]);
    $assignment = RondaAssignment::factory()->for($schedule)->create();

    expect($this->report->missingCheckins($schedule->date)->pluck('id'))->toContain($assignment->id);
});
