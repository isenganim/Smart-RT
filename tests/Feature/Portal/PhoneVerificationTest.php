<?php

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-verify:127.0.0.1');
    $this->household = Household::factory()->create();
});

it('confirms a registered active phone', function () {
    Resident::factory()->for($this->household)->create([
        'name' => 'Andi',
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.verify')
        ->set('phone', '0812-3456-7890')
        ->call('check')
        ->assertSet('verified', true)
        ->assertSee('Andi');
});

it('shows a friendly message for an unknown phone', function () {
    Volt::test('portal.verify')
        ->set('phone', '0899-0000-0000')
        ->call('check')
        ->assertSet('verified', false)
        ->assertSee('belum terdaftar');
});

it('blocks excessive verification attempts', function () {
    $component = Volt::test('portal.verify');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->call('check');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
