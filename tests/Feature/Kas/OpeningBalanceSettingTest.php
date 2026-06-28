<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Setting;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('persists the opening balance and date from settings', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.settings.index')
        ->set('iuran_amount', 500)
        ->set('denda_amount', 5000)
        ->set('kas_opening_balance', 250000)
        ->set('kas_opening_date', '2026-06-01')
        ->call('requestSave')
        ->call('save')
        ->assertHasNoErrors();

    expect((int) Setting::get('kas_opening_balance'))->toBe(250000)
        ->and((string) Setting::get('kas_opening_date'))->toBe('2026-06-01');
});
