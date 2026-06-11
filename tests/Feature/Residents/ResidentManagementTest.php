<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->household = Household::factory()->create();
});

it('blocks guests from resident management', function () {
    $this->get('/dashboard/warga')->assertRedirect('/login');
});

it('lists households with their family members on resident management', function () {
    $household = Household::factory()->create([
        'house_number' => 'No. 7',
        'head_name' => 'Pak Budi',
        'address' => 'Jl. Melati',
    ]);

    Resident::factory()->for($household)->create([
        'name' => 'Budi Santoso',
        'phone' => '81211111111',
        'is_active' => true,
    ]);

    Resident::factory()->for($household)->create([
        'name' => 'Siti Aminah',
        'phone' => '81222222222',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->get('/dashboard/warga')
        ->assertOk()
        ->assertSee('Pak Budi')
        ->assertSee('No. 7')
        ->assertSee('Budi Santoso')
        ->assertSee('Siti Aminah')
        ->assertSee('2 anggota aktif');
});

it('opens the family member modal for a selected household', function () {
    $household = Household::factory()->create([
        'house_number' => 'No. 8',
        'head_name' => 'Ibu Rina',
    ]);

    Resident::factory()->for($household)->create([
        'name' => 'Rina Kartika',
        'phone' => '81233333333',
        'ronda_notes' => 'Jaga bersama anak',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $household->id)
        ->assertSet('editingHouseholdId', $household->id)
        ->assertSee('Kelola Anggota Keluarga')
        ->assertSee('Ibu Rina')
        ->assertSee('Rina Kartika')
        ->assertSee('081233333333')
        ->assertSee('Jaga bersama anak');
});

it('creates multiple family members for one household from the modal', function () {
    $household = Household::factory()->create([
        'house_number' => 'No. 9',
        'head_name' => 'Pak Dedi',
    ]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $household->id)
        ->set('memberRows', [
            [
                'id' => null,
                'name' => 'Dedi Pratama',
                'phone' => '081244444444',
                'ronda_notes' => 'Bisa ronda malam',
                'is_active' => true,
                '_delete' => false,
            ],
            [
                'id' => null,
                'name' => 'Maya Pratama',
                'phone' => '081255555555',
                'ronda_notes' => '',
                'is_active' => true,
                '_delete' => false,
            ],
        ])
        ->call('saveFamilyMembers')
        ->assertHasNoErrors()
        ->assertSet('editingHouseholdId', null);

    expect(Resident::query()->where('household_id', $household->id)->where('name', 'Dedi Pratama')->exists())->toBeTrue();
    expect(Resident::query()->where('household_id', $household->id)->where('name', 'Maya Pratama')->exists())->toBeTrue();
    expect(Resident::query()->where('name', 'Dedi Pratama')->value('phone'))->toBe('81244444444');
    expect(AuditLog::query()->where('action', 'resident.created')->count())->toBe(2);
});

it('creates a resident with a normalized phone and audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->set('memberRows', [
            [
                'id' => null,
                'name' => 'Andi',
                'phone' => '0812-3456-7890',
                'ronda_notes' => '',
                'is_active' => true,
                '_delete' => false,
            ],
        ])
        ->call('saveFamilyMembers')
        ->assertHasNoErrors();

    $resident = Resident::query()->where('name', 'Andi')->first();
    expect($resident)->not->toBeNull();
    expect($resident->household_id)->toBe($this->household->id);
    expect($resident->phone)->toBe('81234567890');
    expect(AuditLog::query()->where('action', 'resident.created')->exists())->toBeTrue();
});

it('updates existing family members from the same modal', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'name' => 'Nama Lama',
        'phone' => '81266666666',
        'ronda_notes' => null,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->set('memberRows.0.name', 'Nama Baru')
        ->set('memberRows.0.phone', '081266666666')
        ->set('memberRows.0.ronda_notes', 'Catatan baru')
        ->call('saveFamilyMembers')
        ->assertHasNoErrors();

    $resident->refresh();

    expect($resident->name)->toBe('Nama Baru');
    expect($resident->phone)->toBe('81266666666');
    expect($resident->ronda_notes)->toBe('Catatan baru');
    expect(AuditLog::query()->where('action', 'resident.updated')->exists())->toBeTrue();
});

it('deactivates an existing family member when removed from the modal', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'name' => 'Anggota Dihapus',
        'phone' => '81277777777',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->call('removeMemberRow', 0)
        ->call('saveFamilyMembers')
        ->assertHasNoErrors();

    expect($resident->fresh()->is_active)->toBeFalse();
    expect(AuditLog::query()->where('action', 'resident.removed')->exists())->toBeTrue();
});

it('rejects a duplicate active phone', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->set('memberRows', [
            [
                'id' => null,
                'name' => 'Dewi',
                'phone' => '081234567890',
                'ronda_notes' => '',
                'is_active' => true,
                '_delete' => false,
            ],
        ])
        ->call('saveFamilyMembers')
        ->assertHasErrors('memberRows.0.phone');
});

it('rejects duplicate active phone numbers within the same family modal submission', function () {
    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->set('memberRows', [
            [
                'id' => null,
                'name' => 'Anggota Satu',
                'phone' => '081288888888',
                'ronda_notes' => '',
                'is_active' => true,
                '_delete' => false,
            ],
            [
                'id' => null,
                'name' => 'Anggota Dua',
                'phone' => '+62 812 8888 8888',
                'ronda_notes' => '',
                'is_active' => true,
                '_delete' => false,
            ],
        ])
        ->call('saveFamilyMembers')
        ->assertHasErrors('memberRows.1.phone');

    expect(Resident::query()->where('household_id', $this->household->id)->count())->toBe(0);
});

it('allows editing a resident keeping its own phone', function () {
    $resident = Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('openFamilyModal', $this->household->id)
        ->set('memberRows.0.name', 'Nama Baru')
        ->call('saveFamilyMembers')
        ->assertHasNoErrors();

    expect($resident->fresh()->name)->toBe('Nama Baru');
});
