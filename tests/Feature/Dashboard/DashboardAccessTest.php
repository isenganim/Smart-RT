<?php

use App\Enums\UserRole;
use App\Models\User;

it('redirects guests from dashboard to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

it('allows admin rt to open dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Pengurus');
});

it('allows bendahara to open dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::BENDAHARA]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Pengurus');
});
