<?php

use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->service = app(ExpenseService::class);
    $this->actor = User::factory()->create();
});

it('records an expense as a negative pengeluaran transaction', function () {
    $tx = $this->service->record(
        Carbon::parse('2026-06-10'),
        75000,
        'Konsumsi ronda malam',
        'Konsumsi Ronda',
        $this->actor,
    );

    expect($tx->type)->toBe(TransactionType::PENGELUARAN)
        ->and($tx->amount)->toBe(-75000)
        ->and($tx->category)->toBe('Konsumsi Ronda')
        ->and($tx->reason)->toBe('Konsumsi ronda malam')
        ->and($tx->source)->toBe('manual')
        ->and($tx->date->toDateString())->toBe('2026-06-10')
        ->and($tx->recorded_by)->toBe($this->actor->id);
});

it('writes an audit log when recording an expense', function () {
    $tx = $this->service->record(Carbon::parse('2026-06-10'), 50000, 'Beli sapu', 'Kebersihan', $this->actor);

    expect(AuditLog::query()
        ->where('action', 'kas.expense_recorded')
        ->where('subject_id', $tx->id)
        ->exists())->toBeTrue();
});

it('rejects non-positive amounts', function () {
    expect(fn () => $this->service->record(Carbon::parse('2026-06-10'), 0, 'X', null, $this->actor))
        ->toThrow(InvalidArgumentException::class);
});

it('normalizes blank category to null', function () {
    $tx = $this->service->record(Carbon::parse('2026-06-10'), 1000, 'Lain-lain', '  ', $this->actor);

    expect($tx->category)->toBeNull();
});

it('reduces the monthly net via the active scope', function () {
    CashTransaction::factory()->create(['date' => '2026-06-05', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 10000]);
    $this->service->record(Carbon::parse('2026-06-10'), 4000, 'Token listrik', 'Lainnya', $this->actor);

    $net = (int) CashTransaction::query()->active()->whereBetween('date', ['2026-06-01', '2026-06-30'])->sum('amount');

    expect($net)->toBe(6000);
});
