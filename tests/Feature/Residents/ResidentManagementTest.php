<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->household = Household::factory()->create();
});

it('blocks guests from resident management', function () {
    $this->get('/dashboard/warga')->assertRedirect('/login');
});

it('creates a resident with a normalized phone and audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->set('household_id', $this->household->id)
        ->set('name', 'Andi')
        ->set('phone', '0812-3456-7890')
        ->call('save')
        ->assertHasNoErrors();

    $resident = Resident::query()->where('name', 'Andi')->first();
    expect($resident)->not->toBeNull();
    expect($resident->phone)->toBe('81234567890');
    expect(AuditLog::query()->where('action', 'resident.created')->exists())->toBeTrue();
});

it('rejects a duplicate active phone', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->set('household_id', $this->household->id)
        ->set('name', 'Dewi')
        ->set('phone', '081234567890')
        ->call('save')
        ->assertHasErrors('phone');
});

it('allows editing a resident keeping its own phone', function () {
    $resident = Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('edit', $resident->id)
        ->set('name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($resident->fresh()->name)->toBe('Nama Baru');
});
