<?php

use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-28 09:00:00');
});

it('marks a future schedule as upcoming', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-30']);

    expect($schedule->status())->toBe('upcoming');
});

it('marks today as ongoing', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-28']);

    expect($schedule->status())->toBe('ongoing');
});

it('marks a past schedule with full attendance as done', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();

    expect($schedule->status())->toBe('done');
});

it('marks a past schedule with missing check-ins as missed', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();
    RondaAssignment::factory()->for($schedule)->create(); // belum check-in

    expect($schedule->status())->toBe('missed');
});

it('marks a past schedule with no assignments as missed', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);

    expect($schedule->status())->toBe('missed');
});

it('uses withCount aliases when available without extra queries', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();

    $loaded = RondaSchedule::query()
        ->withCount(['assignments', 'assignments as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
        ->find($schedule->id);

    expect($loaded->status())->toBe('done');
});

it('renders the status badge on the schedule list', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::ADMIN_RT]);

    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->create(); // belum check-in → terlewat

    $this->actingAs($admin)
        ->get('/dashboard/ronda')
        ->assertOk()
        ->assertSee('Terlewat');
});

it('counts assignments that never checked in', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();
    RondaAssignment::factory()->for($schedule)->create();
    RondaAssignment::factory()->for($schedule)->create();

    expect($schedule->absentCount())->toBe(2);
});

it('reports zero absent when no one is assigned', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);

    expect($schedule->absentCount())->toBe(0);
});

it('shows the absent count and a denda deep link for missed schedules', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::ADMIN_RT]);

    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->create();
    RondaAssignment::factory()->for($schedule)->create();

    $this->actingAs($admin)
        ->get('/dashboard/ronda')
        ->assertOk()
        ->assertSee('2 belum check-in')
        ->assertSee(route('denda.index', ['date' => '2026-06-20']), false)
        ->assertSee('Proses Denda');
});

it('does not show the denda link for completed schedules', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::ADMIN_RT]);

    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-20']);
    RondaAssignment::factory()->for($schedule)->checkedIn()->create();

    $this->actingAs($admin)
        ->get('/dashboard/ronda')
        ->assertOk()
        ->assertDontSee('Proses Denda');
});
