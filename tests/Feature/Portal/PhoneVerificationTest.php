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
        ->assertDontSee('Andi')
        ->assertSee('Nomor ini sudah terdaftar di sistem RT.');
});

it('renders an accessible phone field', function () {
    $this->get('/cek-nomor')
        ->assertOk()
        ->assertSee('for="phone"', false)
        ->assertSee('id="phone"', false)
        ->assertSee('autocomplete="tel"', false);
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

it('redirects unverified users from gated portal routes to verify page', function () {
    $this->get('/checkin-ronda')->assertRedirect(route('portal.verify'));
    $this->get('/lapor')->assertRedirect(route('portal.verify'));
    $this->get('/surat')->assertRedirect(route('portal.verify'));
    $this->get('/voting')->assertRedirect(route('portal.verify'));
    $this->get('/scan-iuran')->assertRedirect(route('portal.verify'));
});

it('allows verified users to access gated portal routes', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $this->withSession(['portal_verified_phone' => '81234567890'])
        ->get('/checkin-ronda')
        ->assertOk();
});
