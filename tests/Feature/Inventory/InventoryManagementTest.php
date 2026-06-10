<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('protects inventory management from guests and non pengurus users', function () {
    $this->get('/dashboard/inventaris')->assertRedirect('/login');

    $regularUser = User::factory()->create();
    $this->actingAs($regularUser)
        ->get('/dashboard/inventaris')
        ->assertForbidden();
});

it('lists inventory items for pengurus', function () {
    InventoryItem::factory()->create(['name' => 'Tenda Biru']);

    $this->actingAs($this->admin)
        ->get('/dashboard/inventaris')
        ->assertOk()
        ->assertSee('Tenda Biru');
});

it('creates an inventory item and audits it', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->set('name', 'Sound System')
        ->set('condition', ItemCondition::BAIK->value)
        ->set('location', 'Gudang RT')
        ->set('notes', 'Dua speaker dan satu mixer.')
        ->call('save')
        ->assertHasNoErrors();

    $item = InventoryItem::query()->where('name', 'Sound System')->firstOrFail();

    expect($item->condition)->toBe(ItemCondition::BAIK)
        ->and($item->status)->toBe(ItemStatus::TERSEDIA)
        ->and($item->location)->toBe('Gudang RT')
        ->and(AuditLog::query()
            ->where('action', 'inventory.created')
            ->where('subject_id', $item->id)
            ->exists())->toBeTrue();
});

it('validates inventory item input with backed enums', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->set('name', '')
        ->set('condition', 'sempurna')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'condition']);

    expect(InventoryItem::query()->count())->toBe(0);
});

it('updates condition and location and audits the changes', function () {
    $item = InventoryItem::factory()->create([
        'condition' => ItemCondition::BAIK,
        'location' => 'Sekretariat RT',
    ]);
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->call('edit', $item->id)
        ->set('condition', ItemCondition::RUSAK_RINGAN->value)
        ->set('location', 'Rumah Pak RT')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->condition)->toBe(ItemCondition::RUSAK_RINGAN)
        ->and($item->fresh()->location)->toBe('Rumah Pak RT')
        ->and(AuditLog::query()
            ->where('action', 'inventory.updated')
            ->where('subject_id', $item->id)
            ->exists())->toBeTrue();
});

it('lends and returns an inventory item with audit logs', function () {
    $item = InventoryItem::factory()->create([
        'status' => ItemStatus::TERSEDIA,
        'location' => 'Gudang RT',
    ]);
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->call('startLend', $item->id)
        ->set('holder', 'Pak Budi')
        ->call('lend')
        ->assertHasNoErrors();

    expect($item->fresh()->status)->toBe(ItemStatus::DIPINJAM)
        ->and($item->fresh()->holder)->toBe('Pak Budi')
        ->and(AuditLog::query()->where('action', 'inventory.lent')->exists())->toBeTrue();

    Volt::test('dashboard.inventory.index')->call('returnItem', $item->id);

    expect($item->fresh()->status)->toBe(ItemStatus::TERSEDIA)
        ->and($item->fresh()->holder)->toBeNull()
        ->and(AuditLog::query()->where('action', 'inventory.returned')->exists())->toBeTrue();
});

it('requires a holder and only lends available items', function () {
    $available = InventoryItem::factory()->create();
    $inactive = InventoryItem::factory()->create(['status' => ItemStatus::TIDAK_AKTIF]);
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->call('startLend', $available->id)
        ->set('holder', '')
        ->call('lend')
        ->assertHasErrors(['holder' => 'required']);

    Volt::test('dashboard.inventory.index')
        ->call('startLend', $inactive->id)
        ->set('holder', 'Pak Budi')
        ->call('lend')
        ->assertHasErrors(['holder']);

    expect($available->fresh()->status)->toBe(ItemStatus::TERSEDIA)
        ->and($inactive->fresh()->status)->toBe(ItemStatus::TIDAK_AKTIF);
});

it('deactivates and reactivates an item with audit logs', function () {
    $item = InventoryItem::factory()->create();
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')->call('toggleActive', $item->id);

    expect($item->fresh()->status)->toBe(ItemStatus::TIDAK_AKTIF);

    Volt::test('dashboard.inventory.index')->call('toggleActive', $item->id);

    expect($item->fresh()->status)->toBe(ItemStatus::TERSEDIA)
        ->and(AuditLog::query()
            ->where('action', 'inventory.status_changed')
            ->where('subject_id', $item->id)
            ->count())->toBe(2);
});

it('rolls back inventory creation when audit logging fails', function () {
    $this->actingAs($this->admin);

    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_inventory_audit_insert
        BEFORE INSERT ON audit_logs
        WHEN NEW.action = 'inventory.created'
        BEGIN
            SELECT RAISE(ABORT, 'simulated audit failure');
        END
        SQL);

    try {
        expect(fn () => Volt::test('dashboard.inventory.index')
            ->set('name', 'Barang tanpa audit')
            ->set('condition', ItemCondition::BAIK->value)
            ->call('save'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_inventory_audit_insert');
    }

    expect(InventoryItem::query()->where('name', 'Barang tanpa audit')->exists())->toBeFalse();
});

it('shows inventory in dashboard navigation', function () {
    $source = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

    expect($source)
        ->toContain("'label' => 'Inventaris'")
        ->toContain("'route' => 'inventory.index'");
});
