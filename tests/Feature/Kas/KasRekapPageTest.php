<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
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

it('shows the recap for the submitted query date', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard/kas?date=2026-06-14')
        ->assertOk()
        ->assertSee('14 Juni 2026');
});

it('builds the display button URL from the selected date', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/index.blade.php'));

    expect($source)
        ->toContain('x-bind:href')
        ->toContain('?date=\' + raw');
});

it('uses the shared admin design system on the kas overview', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/index.blade.php'));

    expect($source)
        ->toContain('<x-admin.page-header')
        ->toContain('<x-admin.metric')
        ->toContain('<x-admin.panel')
        ->toContain('<x-admin.empty-state');
});

it('opens the native date picker from an explicit calendar button', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/index.blade.php'));

    expect($source)
        ->toContain('type="button"')
        ->toContain('picker.showPicker()')
        ->toContain('picker.click()');
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

it('shows cancellation confirmation in an accessible dismissible dialog', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/kas/transactions.blade.php'));

    expect($source)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby="cancel-transaction-title"')
        ->toContain('aria-describedby="cancel-transaction-description"')
        ->toContain('wire:keydown.escape.window="cancelCancel"')
        ->toContain('wire:click="cancelCancel"')
        ->toContain('@keydown.tab="trapFocus($event)"');
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

it('defaults the transaksi list to today', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.kas.transactions')
        ->assertSet('date', today()->toDateString());
});

it('respects a valid transaksi query date', function () {
    CashTransaction::factory()->create(['date' => '2026-06-14', 'amount' => 500]);

    $this->actingAs($this->admin)
        ->get('/dashboard/kas/transaksi?date=2026-06-14')
        ->assertOk()
        ->assertSee('14 Juni 2026');
});

it('handles a malformed transaksi date safely', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard/kas/transaksi?date=not-a-date')
        ->assertOk()
        ->assertSee('Daftar Transaksi Kas');
});

it('scopes the transaksi list to the selected day', function () {
    $onDay = Household::factory()->create(['house_number' => 'A-99']);
    $otherDay = Household::factory()->create(['house_number' => 'B-11']);
    CashTransaction::factory()->create(['date' => '2026-06-14', 'household_id' => $onDay->id, 'amount' => 500]);
    CashTransaction::factory()->create(['date' => '2026-06-10', 'household_id' => $otherDay->id, 'amount' => 500]);

    $this->actingAs($this->admin)
        ->get('/dashboard/kas/transaksi?date=2026-06-14')
        ->assertOk()
        ->assertSee('A-99')
        ->assertDontSee('B-11');
});

it('summarizes active-only totals for the selected day', function () {
    CashTransaction::factory()->create(['date' => '2026-06-14', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);
    $cancelled = CashTransaction::factory()->create(['date' => '2026-06-14', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);
    $cancelled->forceFill(['cancelled_at' => now(), 'reason' => 'Salah input'])->save();

    $this->actingAs($this->admin)
        ->get('/dashboard/kas/transaksi?date=2026-06-14')
        ->assertOk()
        ->assertDontSee('Rp5.500');
});
