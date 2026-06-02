<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->household = Household::factory()->create();
});

it('blocks guests from ronda management', function () {
    $this->get('/dashboard/ronda')->assertRedirect('/login');
});

it('creates a schedule and writes an audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.index')
        ->set('date', today()->addDay()->toDateString())
        ->set('notes', 'Ronda malam')
        ->call('save')
        ->assertHasNoErrors();

    expect(RondaSchedule::query()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'ronda.schedule.created')->exists())->toBeTrue();
});

it('rejects a duplicate schedule date', function () {
    RondaSchedule::factory()->create(['date' => today()->addDay()->toDateString()]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.index')
        ->set('date', today()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors('date');
});

it('rejects an invalid schedule date without throwing', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.index')
        ->set('date', 'bukan-tanggal')
        ->call('save')
        ->assertHasErrors('date');
});

it('assigns an active resident to a schedule', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.show', ['schedule' => $schedule])
        ->set('residentId', $resident->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect($schedule->assignments()->where('resident_id', $resident->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'ronda.assignment.added')->exists())->toBeTrue();
});

it('rejects assigning an inactive resident to a schedule', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => false]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.show', ['schedule' => $schedule])
        ->set('residentId', $resident->id)
        ->call('assign')
        ->assertHasErrors('residentId');

    expect($schedule->assignments()->where('resident_id', $resident->id)->exists())->toBeFalse();
});

it('assigns an active resident through the livewire update endpoint', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    $this->actingAs($this->admin);

    $html = $this->get(route('ronda.show', $schedule))
        ->assertOk()
        ->getContent();

    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $snapshots);

    $snapshot = collect($snapshots[1])
        ->map(fn (string $snapshot) => html_entity_decode($snapshot, ENT_QUOTES))
        ->first(fn (string $snapshot) => str_contains($snapshot, '"name":"dashboard.ronda.show"'));

    $this->postJson(route('default-livewire.update'), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['residentId' => (string) $resident->id],
            'calls' => [[
                'method' => 'assign',
                'params' => [],
                'metadata' => [],
            ]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    expect($schedule->assignments()->where('resident_id', $resident->id)->exists())->toBeTrue();
});

it('shows checked in times with the wib timezone label', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    RondaAssignment::factory()
        ->for($schedule)
        ->for($resident)
        ->create(['checked_in_at' => Carbon::create(2026, 6, 1, 20, 15, 0, 'Asia/Jakarta')]);

    $this->actingAs($this->admin);

    $this->get(route('ronda.show', $schedule))
        ->assertOk()
        ->assertSee('Hadir (20:15 WIB)');
});

it('does not assign the same resident twice', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    $this->actingAs($this->admin);

    $component = Volt::test('dashboard.ronda.show', ['schedule' => $schedule]);
    $component->set('residentId', $resident->id)->call('assign');
    $component->set('residentId', $resident->id)->call('assign');

    expect($schedule->assignments()->where('resident_id', $resident->id)->count())->toBe(1);
});
