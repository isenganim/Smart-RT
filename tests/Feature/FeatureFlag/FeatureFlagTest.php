<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FeatureSetting;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::flush();
});

it('defaults to enabled when no setting row exists', function () {
    expect(Feature::enabled('voting'))->toBeTrue();
});

it('reflects a stored disabled flag', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => false]);

    expect(Feature::enabled('voting'))->toBeFalse();
});

it('blocks a disabled dashboard route with 403', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => false]);
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard/voting')
        ->assertForbidden();
});

it('allows an enabled dashboard route', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => true]);
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard/voting')
        ->assertOk();
});

it('blocks a disabled portal route with 403', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => false]);

    $this->get('/voting')->assertForbidden();
});

it('hides a disabled module from the sidebar', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => false]);
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('votes.index'));
});

it('hides a disabled module tile from the portal home', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Pemungutan Suara')
        ->assertSee('Cek Nomor HP');
});

it('lets admin rt open the settings page', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard/pengaturan')
        ->assertOk()
        ->assertSee('Pengaturan Fitur');
});

it('forbids bendahara from the settings page', function () {
    $user = User::factory()->create(['role' => UserRole::BENDAHARA]);

    $this->actingAs($user)
        ->get('/dashboard/pengaturan')
        ->assertForbidden();
});

it('lets admin toggle a feature off and persists it', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => true]);
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user);

    Volt::test('dashboard.settings.index')
        ->call('toggle', 'voting')
        ->call('save');

    expect(FeatureSetting::where('key', 'voting')->value('is_enabled'))->toBeFalse();
    expect(Feature::enabled('voting'))->toBeFalse();
});

it('records an audit log entry when a feature is toggled', function () {
    FeatureSetting::create(['key' => 'voting', 'is_enabled' => true]);
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user);

    Volt::test('dashboard.settings.index')
        ->call('toggle', 'voting')
        ->call('save');

    expect(AuditLog::where('action', 'feature.disabled')->exists())->toBeTrue();
});
