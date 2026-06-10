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
