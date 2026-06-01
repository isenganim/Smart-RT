<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Services\RondaCheckin;

beforeEach(function () {
    $this->service = app(RondaCheckin::class);
    $this->household = Household::factory()->create();
    $this->schedule = RondaSchedule::factory()->create(['date' => today()]);
});

it('checks in a scheduled active resident', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $result = $this->service->checkIn('0812-3456-7890', $this->schedule->date);

    expect($result->success())->toBeTrue();
    expect($result->assignment->fresh()->hasCheckedIn())->toBeTrue();
});

it('rejects an unregistered phone', function () {
    $result = $this->service->checkIn('0899-0000-0000', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('rejects a resident not scheduled that day', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->service->checkIn('81234567890', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP tidak terjadwal ronda hari ini.');
});

it('rejects a second check-in on the same date', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->checkedIn()->create();

    $result = $this->service->checkIn('81234567890', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Anda sudah check-in untuk tanggal ini.');
});

it('rejects when no schedule exists for the date', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->service->checkIn('81234567890', today()->addDay());

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Belum ada jadwal ronda untuk tanggal ini.');
});
