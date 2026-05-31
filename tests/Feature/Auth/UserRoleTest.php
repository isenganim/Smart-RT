<?php

use App\Enums\UserRole;
use App\Models\User;

it('casts user role to enum', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    expect($user->fresh()->role)->toBe(UserRole::ADMIN_RT);
});

it('detects pengurus users', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $bendahara = User::factory()->create(['role' => UserRole::BENDAHARA]);

    expect($admin->isPengurus())->toBeTrue();
    expect($bendahara->isPengurus())->toBeTrue();
});
