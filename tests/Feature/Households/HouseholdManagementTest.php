<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from household management', function () {
    $this->get('/dashboard/rumah')->assertRedirect('/login');
});

it('lists households for pengurus', function () {
    Household::factory()->create(['head_name' => 'Budi Santoso']);

    $this->actingAs($this->admin)
        ->get('/dashboard/rumah')
        ->assertOk()
        ->assertSee('Budi Santoso');
});

it('creates a household and writes an audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('households.index')
        ->set('house_number', 'No. 12')
        ->set('address', 'Jl. Mawar')
        ->set('head_name', 'Siti')
        ->call('save')
        ->assertHasNoErrors();

    $household = Household::query()->where('head_name', 'Siti')->first();
    expect($household)->not->toBeNull();
    expect($household->qr_token)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'household.created')->exists())->toBeTrue();
});

it('renders a qr svg for a household', function () {
    $household = Household::factory()->create();

    $this->actingAs($this->admin)
        ->get("/dashboard/rumah/{$household->id}/qr")
        ->assertOk()
        ->assertSee('<svg', false);
});
