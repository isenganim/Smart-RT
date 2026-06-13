<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\RondaScanSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from scan session management', function () {
    $this->get('/dashboard/sesi-scan')->assertRedirect('/login');
});

it('creates a session with a generated pin and audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->set('date', today()->toDateString())
        ->set('starts_at', today()->setTime(18, 0)->format('Y-m-d\\TH:i'))
        ->set('ends_at', today()->addDay()->setTime(6, 0)->format('Y-m-d\\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $session = RondaScanSession::query()->first();
    expect($session)->not->toBeNull();
    expect($session->pin)->toMatch('/^\d{4,6}$/');
    expect(AuditLog::query()->where('action', 'ronda.scan_session.created')->exists())->toBeTrue();
});

it('rejects a duplicate session date', function () {
    RondaScanSession::factory()->create(['date' => today()]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->set('date', today()->toDateString())
        ->set('starts_at', today()->setTime(18, 0)->format('Y-m-d\\TH:i'))
        ->set('ends_at', today()->addDay()->setTime(6, 0)->format('Y-m-d\\TH:i'))
        ->call('save')
        ->assertHasErrors('date');
});

it('regenerates a pin and audits the change', function () {
    $session = RondaScanSession::factory()->create(['pin' => '111111']);

    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->call('regenerate', $session->id);

    expect($session->fresh()->pin)->not->toBe('111111');
    expect(AuditLog::query()->where('action', 'ronda.scan_session.pin_regenerated')->exists())->toBeTrue();
});

it('shows a future session as not started instead of expired', function () {
    Carbon::setTestNow('2026-06-13 15:00:00');

    RondaScanSession::factory()->create([
        'date' => '2026-06-13',
        'starts_at' => '2026-06-13 19:00:00',
        'ends_at' => '2026-06-13 22:00:00',
    ]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->assertSee('Belum Mulai')
        ->assertDontSee('Kedaluwarsa');

    Carbon::setTestNow();
});
