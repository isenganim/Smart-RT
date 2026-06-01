<?php

use App\Models\Household;
use App\Models\Resident;
use App\Services\ResidentLookup;

beforeEach(function () {
    $this->lookup = new ResidentLookup();
    $this->household = Household::factory()->create();
});

it('resolves an active resident by raw phone input', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->lookup->resolve('0812-3456-7890');

    expect($result->found())->toBeTrue();
    expect($result->resident->is($resident))->toBeTrue();
    expect($result->message)->toBeNull();
});

it('reports an unknown phone as not registered', function () {
    $result = $this->lookup->resolve('0899-0000-0000');

    expect($result->found())->toBeFalse();
    expect($result->resident)->toBeNull();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('rejects a phone that belongs only to an inactive resident', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => false,
    ]);

    $result = $this->lookup->resolve('81234567890');

    expect($result->found())->toBeFalse();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('treats blank input as not registered without querying', function () {
    $result = $this->lookup->resolve('');

    expect($result->found())->toBeFalse();
});
