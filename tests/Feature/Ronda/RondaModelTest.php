<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;

it('casts the schedule date and has many assignments', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-01']);
    $resident = Resident::factory()->for(Household::factory())->create();
    RondaAssignment::factory()->for($schedule)->for($resident)->create();

    expect($schedule->date->toDateString())->toBe('2026-06-01');
    expect($schedule->assignments)->toHaveCount(1);
});

it('marks an assignment present when checked in', function () {
    $assignment = RondaAssignment::factory()->create(['checked_in_at' => now()]);

    expect($assignment->hasCheckedIn())->toBeTrue();
});

it('reports an assignment without check-in as absent', function () {
    $assignment = RondaAssignment::factory()->create(['checked_in_at' => null]);

    expect($assignment->hasCheckedIn())->toBeFalse();
});

it('belongs to a resident and a schedule', function () {
    $assignment = RondaAssignment::factory()->create();

    expect($assignment->resident)->not->toBeNull();
    expect($assignment->rondaSchedule)->not->toBeNull();
});
