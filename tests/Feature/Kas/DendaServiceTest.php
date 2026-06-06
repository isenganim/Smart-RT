<?php

use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Services\DendaService;

beforeEach(function () {
    $this->service = app(DendaService::class);
    $this->schedule = RondaSchedule::factory()->create(['date' => today()->subDay()]);
});

it('lists scheduled residents who did not check in for a date', function () {
    $present = Resident::factory()->for(Household::factory())->create();
    $absent = Resident::factory()->for(Household::factory())->create();

    RondaAssignment::factory()->for($this->schedule)->for($present)->checkedIn()->create();
    RondaAssignment::factory()->for($this->schedule)->for($absent)->create();

    $candidates = $this->service->candidates($this->schedule->date);

    expect($candidates)->toHaveCount(1);
    expect($candidates->first()->resident_id)->toBe($absent->id);
});

it('records a 5000 denda for an absent assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $tx = $this->service->fine($assignment);

    expect($tx->amount)->toBe(5000);
    expect($tx->type->value)->toBe('denda');
    expect($tx->resident_id)->toBe($resident->id);
    expect($tx->date->toDateString())->toBe($this->schedule->date->toDateString());
});

it('is idempotent and does not double-fine the same assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->service->fine($assignment);
    $this->service->fine($assignment);

    expect(CashTransaction::query()->denda()->count())->toBe(1);
});

it('refuses to fine an assignment that has checked in', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->checkedIn()->create();

    expect(fn () => $this->service->fine($assignment))
        ->toThrow(InvalidArgumentException::class);
});
