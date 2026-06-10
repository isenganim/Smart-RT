<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from household management', function () {
    $this->get('/dashboard/rumah')->assertRedirect('/login');
});

it('lists households for pengurus', function () {
    Household::factory()->create(['head_name' => 'Budi Santoso']);

    $this->actingAs($this->admin)
        ->get('/dashboard/rumah')
        ->assertOk()
        ->assertSee('Budi Santoso');
});

it('shows a create modal trigger instead of the inline household form', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard/rumah')
        ->assertOk()
        ->assertSee('Tambah Rumah/KK Baru')
        ->assertDontSee('Isi nomor rumah dan nama kepala keluarga untuk operasional RT.');
});

it('opens a clean create modal', function () {
    $this->actingAs($this->admin);

    Volt::test('households.index')
        ->assertSet('showFormModal', false)
        ->call('openCreateModal')
        ->assertSet('showFormModal', true)
        ->assertSet('editingId', null)
        ->assertSet('house_number', '')
        ->assertSet('address', '')
        ->assertSet('head_name', '');
});

it('creates a household and writes an audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('households.index')
        ->call('openCreateModal')
        ->set('house_number', 'No. 12')
        ->set('address', 'Jl. Mawar')
        ->set('head_name', 'Siti')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false);

    $household = Household::query()->where('head_name', 'Siti')->first();
    expect($household)->not->toBeNull();
    expect($household->qr_token)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'household.created')->exists())->toBeTrue();
});

it('opens an edit modal and updates a household', function () {
    $household = Household::factory()->create([
        'house_number' => 'No. 7',
        'address' => 'Gang Melati',
        'head_name' => 'Budi Santoso',
    ]);

    $this->actingAs($this->admin);

    Volt::test('households.index')
        ->call('edit', $household->id)
        ->assertSet('showFormModal', true)
        ->assertSet('editingId', $household->id)
        ->assertSet('house_number', 'No. 7')
        ->assertSet('address', 'Gang Melati')
        ->assertSet('head_name', 'Budi Santoso')
        ->set('house_number', 'No. 8')
        ->set('head_name', 'Budi Saputra')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false)
        ->assertSet('editingId', null);

    $household->refresh();

    expect($household->house_number)->toBe('No. 8');
    expect($household->head_name)->toBe('Budi Saputra');
    expect(AuditLog::query()->where('action', 'household.updated')->exists())->toBeTrue();
});

it('renders a qr svg for a household', function () {
    $household = Household::factory()->create();

    $this->actingAs($this->admin)
        ->get("/dashboard/rumah/{$household->id}/qr")
        ->assertOk()
        ->assertSee('<svg', false);
});
