<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::BENDAHARA]);
});

it('blocks guests from kas rekap', function () {
    $this->get('/dashboard/kas')->assertRedirect('/login');
});

it('shows daily, weekly, and monthly totals', function () {
    CashTransaction::factory()->count(2)->create(['date' => today(), 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);

    $this->actingAs($this->admin)
        ->get('/dashboard/kas')
        ->assertOk()
        ->assertSee('Rekap Kas')
        ->assertSee('1.000');
});

it('handles malformed recap date input safely', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard/kas?date=not-a-date')
        ->assertOk()
        ->assertSee('Rekap Kas');
});

it('uses the shared admin design system on the kas overview', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/index.blade.php'));

    expect($source)
        ->toContain('<x-admin.page-header')
        ->toContain('<x-admin.metric')
        ->toContain('<x-admin.panel')
        ->toContain('<x-admin.empty-state');
});

it('provides responsive transaction presentations without mobile table overflow', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/transactions.blade.php'));

    expect($source)
        ->toContain('<x-admin.page-header')
        ->toContain('<x-admin.panel')
        ->toContain('hidden md:block')
        ->toContain('md:hidden')
        ->toContain('<x-admin.status-badge')
        ->toContain('<x-admin.button');
});

it('cancels a transaction with a reason from the transactions page', function () {
    $tx = CashTransaction::factory()->create(['amount' => 500]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.kas.transactions')
        ->call('startCancel', $tx->id)
        ->set('reason', 'Salah input')
        ->call('confirmCancel')
        ->assertHasNoErrors();

    expect($tx->fresh()->isCancelled())->toBeTrue();
    expect(CashTransaction::query()->where('type', TransactionType::KOREKSI->value)->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kas.transaction.cancelled')->exists())->toBeTrue();
});

it('requires a reason before cancelling', function () {
    $tx = CashTransaction::factory()->create(['amount' => 500]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.kas.transactions')
        ->call('startCancel', $tx->id)
        ->set('reason', '')
        ->call('confirmCancel')
        ->assertHasErrors('reason');

    expect($tx->fresh()->isCancelled())->toBeFalse();
});
