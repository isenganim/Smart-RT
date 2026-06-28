<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\CashTransaction;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::BENDAHARA]);
});

it('exports the monthly statement as a PDF', function () {
    CashTransaction::factory()->create(['date' => '2026-06-03', 'type' => TransactionType::IURAN_HARIAN, 'amount' => 5000]);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard/kas/laporan-bulanan/export?month=2026-06');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('requires pengurus authentication', function () {
    $this->get('/dashboard/kas/laporan-bulanan/export?month=2026-06')
        ->assertRedirect('/login');
});

it('writes an audit log for the export', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard/kas/laporan-bulanan/export?month=2026-06')
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'kas.statement_exported']);
});
