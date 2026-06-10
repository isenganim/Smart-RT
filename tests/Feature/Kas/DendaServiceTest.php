<?php

use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
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

it('excludes already fined residents from candidates', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->service->fine($assignment);

    $candidates = $this->service->candidates($this->schedule->date);

    expect($candidates)->toBeEmpty();
});

it('records a 5000 denda for an absent assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $actor = User::factory()->create();
    $tx = $this->service->fine($assignment, $actor);

    expect($tx->amount)->toBe(5000);
    expect($tx->type->value)->toBe('denda');
    expect($tx->resident_id)->toBe($resident->id);
    expect($tx->date->toDateString())->toBe($this->schedule->date->toDateString());
    expect(AuditLog::query()
        ->where('action', 'kas.denda.created')
        ->where('subject_type', 'cash_transaction')
        ->where('subject_id', $tx->id)
        ->where('actor_id', $actor->id)
        ->exists())->toBeTrue();
});

it('is idempotent and does not double-fine the same assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->service->fine($assignment);
    $this->service->fine($assignment);

    expect(CashTransaction::query()->denda()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kas.denda.created')->count())->toBe(1);
});

it('allows a new denda after the previous denda was cancelled', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();
    $first = $this->service->fine($assignment);
    $first->update([
        'cancelled_at' => now(),
        'reason' => 'Salah denda',
    ]);

    $second = $this->service->fine($assignment);

    expect($second->id)->not->toBe($first->id);
    expect(CashTransaction::query()->denda()->count())->toBe(2);
    expect(CashTransaction::query()->active()->denda()->count())->toBe(1);
});

it('refuses to fine an assignment that has checked in', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->checkedIn()->create();

    expect(fn () => $this->service->fine($assignment))
        ->toThrow(InvalidArgumentException::class);
});
