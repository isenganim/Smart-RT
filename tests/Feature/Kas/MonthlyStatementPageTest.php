<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\CashTransaction;
use App\Models\FeatureSetting;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 09:00:00');
    $this->admin = User::factory()->create(['role' => UserRole::BENDAHARA]);
});

afterEach(function () {
    Carbon::setTestNow(null);
});

it('renders the statement totals for a bendahara', function () {
    CashTransaction::factory()->create(['date' => '2026-06-03', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);
    CashTransaction::factory()->create(['date' => '2026-06-10', 'type' => TransactionType::PENGELUARAN, 'amount' => -2000, 'category' => 'Kebersihan', 'status' => 'keluar', 'source' => 'manual', 'reason' => 'Sapu']);

    $this->actingAs($this->admin)
        ->get('/dashboard/kas/laporan-bulanan?month=2026-06')
        ->assertOk()
        ->assertSee('Laporan Keuangan Bulanan')
        ->assertSee('Rp5.000')
        ->assertSee('Kebersihan')
        ->assertSee('Rp3.000'); // closing = 5000 - 2000
});

it('records an expense from the page', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.kas.statement')
        ->set('month', '2026-06')
        ->call('openExpense')
        ->set('expense_date', '2026-06-12')
        ->set('expense_amount', 25000)
        ->set('expense_category', 'Perbaikan')
        ->set('expense_description', 'Perbaikan lampu jalan')
        ->call('saveExpense')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cash_transactions', [
        'type' => TransactionType::PENGELUARAN->value,
        'amount' => -25000,
        'category' => 'Perbaikan',
        'date' => '2026-06-12 00:00:00',
        'reason' => 'Perbaikan lampu jalan',
        'recorded_by' => $this->admin->id,
    ]);
});

it('validates expense input', function () {
    Volt::test('dashboard.kas.statement')
        ->call('openExpense')
        ->set('expense_amount', 0)
        ->set('expense_description', '')
        ->call('saveExpense')
        ->assertHasErrors(['expense_amount', 'expense_description']);
});

it('blocks the page when kas feature is disabled', function () {
    FeatureSetting::updateOrCreate(['key' => 'kas'], ['is_enabled' => false]);
    Feature::flush();

    $this->actingAs($this->admin)
        ->get('/dashboard/kas/laporan-bulanan')
        ->assertForbidden();
});
