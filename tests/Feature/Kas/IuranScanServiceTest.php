<?php

use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaScanSession;
use App\Services\IuranScan;

beforeEach(function () {
    $this->service = new IuranScan;
    $this->session = RondaScanSession::factory()->active()->create(['date' => today()]);
    $this->household = Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-1', 'is_active' => true]);
});

it('records a 500 iuran for an unpaid household', function () {
    $result = $this->service->record($this->session, 'HOUSE-TOKEN-1');

    expect($result->paid())->toBeTrue();
    expect($result->transaction->amount)->toBe(500);
    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
    expect(AuditLog::query()
        ->where('action', 'kas.iuran.created')
        ->where('subject_type', 'cash_transaction')
        ->where('subject_id', $result->transaction->id)
        ->exists())->toBeTrue();
});

it('reports already paid on a second scan for the same date', function () {
    $this->service->record($this->session, 'HOUSE-TOKEN-1');

    $result = $this->service->record($this->session, 'HOUSE-TOKEN-1');

    expect($result->paid())->toBeFalse();
    expect($result->status)->toBe('already_paid');
    expect($result->message)->toBe('Iuran rumah ini sudah tercatat hari ini.');
    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kas.iuran.created')->count())->toBe(1);
});

it('allows a new scan after the previous iuran was cancelled', function () {
    $first = $this->service->record($this->session, 'HOUSE-TOKEN-1');
    $first->transaction->update([
        'cancelled_at' => now(),
        'reason' => 'Salah scan rumah',
    ]);

    $result = $this->service->record($this->session, 'HOUSE-TOKEN-1');

    expect($result->paid())->toBeTrue();
    expect(CashTransaction::query()->iuranHarian()->count())->toBe(2);
    expect(CashTransaction::query()->active()->iuranHarian()->count())->toBe(1);
});

it('rejects an unknown qr token', function () {
    $result = $this->service->record($this->session, 'NOPE');

    expect($result->paid())->toBeFalse();
    expect($result->message)->toBe('QR rumah tidak dikenali.');
});

it('rejects a token for an inactive household', function () {
    Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-2', 'is_active' => false]);

    $result = $this->service->record($this->session, 'HOUSE-TOKEN-2');

    expect($result->paid())->toBeFalse();
    expect($result->message)->toBe('QR rumah tidak dikenali.');
});
