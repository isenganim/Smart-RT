<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->schedule = RondaSchedule::factory()->create(['date' => today()->subDay()]);
});

it('blocks guests from denda review', function () {
    $this->get('/dashboard/denda')->assertRedirect('/login');
});

it('shows absent residents as denda candidates', function () {
    $resident = Resident::factory()->for(Household::factory())->create(['name' => 'Joko']);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard/denda?date='.$this->schedule->date->toDateString())
        ->assertOk()
        ->assertSee('Joko');
});

it('sets a 5000 denda after review and audits it', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->actingAs($this->admin);

    Volt::test('dashboard.denda.index', ['date' => $this->schedule->date->toDateString()])
        ->call('fine', $assignment->id)
        ->assertHasNoErrors();

    expect(CashTransaction::query()->denda()->where('amount', 5000)->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kas.denda.created')->exists())->toBeTrue();
});
