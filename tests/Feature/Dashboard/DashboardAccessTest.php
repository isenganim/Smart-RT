<?php

use App\Enums\UserRole;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;

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

it('shows operational metrics and action queue instead of a marketing hero', function () {
    Carbon::setTestNow('2026-06-11 09:00:00');

    try {
        $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);
        Household::factory()->count(2)->create(['is_active' => true]);
        Report::factory()->create();
        LetterRequest::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Yang perlu ditangani')
            ->assertSee('Arus kas 30 hari')
            ->assertSee('Aktivitas terbaru')
            ->assertSee('Rumah belum membayar iuran')
            ->assertSee('h-full min-w-0', escape: false)
            ->assertSee('grid grid-cols-2 gap-4', escape: false)
            ->assertDontSee('Sistem Smart RT Aktif');
    } finally {
        Carbon::setTestNow();
    }
});
