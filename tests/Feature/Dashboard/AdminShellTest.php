<?php

use App\Enums\UserRole;
use App\Models\User;

it('renders grouped desktop navigation and the five-item mobile contract', function () {
    $user = User::factory()->create([
        'name' => 'Ketua RT',
        'role' => UserRole::ADMIN_RT,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ringkasan')
        ->assertSee('Data Warga')
        ->assertSee('Operasional')
        ->assertSee('Layanan')
        ->assertSee('Beranda')
        ->assertSee('Lainnya')
        ->assertSee('Ketua RT')
        ->assertSee('Admin RT');
});

it('marks the current admin destination semantically', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get(route('households.index'))
        ->assertOk()
        ->assertSee('aria-current="page"', escape: false);
});

it('omits empty groups from the mobile more drawer', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, '>Ringkasan<'))->toBe(1)
        ->and(substr_count($html, '>Data Warga<'))->toBe(1)
        ->and(substr_count($html, '>Operasional<'))->toBe(2)
        ->and(substr_count($html, '>Layanan<'))->toBe(2);
});

it('defines focus trapping and restoration for the mobile drawer', function () {
    $source = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

    expect($source)
        ->toContain('@keydown.tab')
        ->toContain('$refs.moreTrigger.focus()')
        ->toContain('x-ref="moreDialog"');
});
