<?php

use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\User;
use App\Services\TransactionCorrection;

beforeEach(function () {
    $this->service = app(TransactionCorrection::class);
    $this->actor = User::factory()->create();
});

it('cancels a transaction and writes an offsetting koreksi row', function () {
    $original = CashTransaction::factory()->create(['amount' => 500]);

    $koreksi = $this->service->cancel($original, 'Salah scan rumah', $this->actor);

    expect($original->fresh()->isCancelled())->toBeTrue();
    expect($original->fresh()->cancelled_by)->toBe($this->actor->id);
    expect($koreksi->type->value)->toBe('koreksi');
    expect($koreksi->amount)->toBe(-500);
    expect($koreksi->reverses_id)->toBe($original->id);
    expect($koreksi->reason)->toBe('Salah scan rumah');
    expect(AuditLog::query()
        ->where('action', 'kas.transaction.cancelled')
        ->where('subject_type', 'cash_transaction')
        ->where('subject_id', $original->id)
        ->where('actor_id', $this->actor->id)
        ->exists())->toBeTrue();
});

it('does not cancel an already cancelled transaction', function () {
    $original = CashTransaction::factory()->create([
        'amount' => 500,
        'cancelled_at' => now(),
        'reason' => 'Sudah dibatalkan',
    ]);

    expect(fn () => $this->service->cancel($original, 'dup', $this->actor))
        ->toThrow(InvalidArgumentException::class);
});

it('requires a reason', function () {
    $original = CashTransaction::factory()->create(['amount' => 500]);

    expect(fn () => $this->service->cancel($original, '   ', $this->actor))
        ->toThrow(InvalidArgumentException::class);
});

it('does not cancel koreksi transactions', function () {
    $koreksi = CashTransaction::factory()->create([
        'type' => TransactionType::KOREKSI,
        'amount' => -500,
        'reason' => 'Salah input',
    ]);

    expect(fn () => $this->service->cancel($koreksi, 'dup', $this->actor))
        ->toThrow(InvalidArgumentException::class, 'Transaksi koreksi tidak bisa dibatalkan.');
});
