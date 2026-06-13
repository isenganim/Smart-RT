<?php

use App\Models\RondaScanSession;
use App\Services\PinGate;

beforeEach(function () {
    $this->gate = new PinGate;
});

it('unlocks with a valid active pin', function () {
    $session = RondaScanSession::factory()->active()->create(['pin' => '654321']);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeTrue();
    expect($result->session->is($session))->toBeTrue();
});

it('rejects an unknown pin', function () {
    RondaScanSession::factory()->active()->create(['pin' => '654321']);

    $result = $this->gate->unlock('000000');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN tidak ditemukan.');
});

it('rejects an expired pin', function () {
    RondaScanSession::factory()->expired()->create(['pin' => '654321']);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN sudah kedaluwarsa.');
});

it('rejects a pin for a session that has not started', function () {
    RondaScanSession::factory()->create([
        'pin' => '654321',
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(3),
    ]);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('Sesi pindai belum dimulai.');
});

it('unlocks an active session when an older duplicate pin is expired', function () {
    RondaScanSession::factory()->expired()->create(['date' => today()->subDay(), 'pin' => '654321']);
    $active = RondaScanSession::factory()->active()->create(['date' => today(), 'pin' => '654321']);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeTrue();
    expect($result->session->is($active))->toBeTrue();
});

it('rejects a blank pin without querying', function () {
    $result = $this->gate->unlock('');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN wajib diisi.');
});
