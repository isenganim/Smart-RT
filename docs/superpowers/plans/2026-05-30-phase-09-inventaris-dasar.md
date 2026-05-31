# Phase 09 Inventaris Dasar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the basic RT inventory module. Pengurus track RT-owned items with name, condition, location or borrower, and availability status, all from the dashboard. This module is pengurus-only: there is no public/warga-facing surface. Optionally expose a read-only public list of RT assets is out of scope; the MVP keeps inventory internal.

**Architecture:** Continue the single Laravel 12 application. One model: `InventoryItem` (name, condition enum, status enum, location/holder text, notes). Status captures whether an item is available, on loan, or out of service; condition captures physical state. All CRUD lives under the authenticated `pengurus` dashboard and audits through `App\Support\Audit`. A lightweight "lend / return" action toggles status and updates the holder field in one place so the common operational flow is one click. No new services are required; the Volt page composes model queries directly, matching the simple-module pattern from Phases 07–08.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, `x-layouts.app`).

---

## Prerequisites

- Phases 01 is complete (and 02–08 do not block this module): `pengurus` middleware, `App\Support\Audit`, and `x-layouts.app` exist. The dashboard nav in `resources/views/components/layouts/app.blade.php` is present.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `app/Enums/ItemCondition.php` (`baik`, `rusak_ringan`, `rusak_berat`).
- Create: `app/Enums/ItemStatus.php` (`tersedia`, `dipinjam`, `tidak_aktif`).
- Create: `database/migrations/*_create_inventory_items_table.php`.
- Create: `app/Models/InventoryItem.php`.
- Create: `database/factories/InventoryItemFactory.php`.
- Modify: `routes/web.php` (dashboard inventaris route).
- Create: `resources/views/livewire/dashboard/inventory/index.blade.php`.
- Modify: `resources/views/components/layouts/app.blade.php` (add Inventaris nav link).
- Test: `tests/Feature/Inventory/InventoryItemModelTest.php`.
- Test: `tests/Feature/Inventory/InventoryManagementTest.php`.

## Task 1: Create the Inventory Item Model

**Files:**

- Create: `app/Enums/ItemCondition.php`
- Create: `app/Enums/ItemStatus.php`
- Create: `database/migrations/*_create_inventory_items_table.php`
- Create: `app/Models/InventoryItem.php`
- Create: `database/factories/InventoryItemFactory.php`
- Test: `tests/Feature/Inventory/InventoryItemModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Inventory/InventoryItemModelTest.php`:

```php
<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;

it('casts condition and status to enums', function () {
    $item = InventoryItem::factory()->create([
        'condition' => ItemCondition::BAIK,
        'status' => ItemStatus::TERSEDIA,
    ]);

    expect($item->fresh()->condition)->toBe(ItemCondition::BAIK);
    expect($item->fresh()->status)->toBe(ItemStatus::TERSEDIA);
});

it('defaults a new item to tersedia and baik', function () {
    $item = InventoryItem::factory()->create();

    expect($item->status)->toBe(ItemStatus::TERSEDIA);
    expect($item->condition)->toBe(ItemCondition::BAIK);
});

it('scopes available items', function () {
    InventoryItem::factory()->create(['status' => ItemStatus::TERSEDIA]);
    InventoryItem::factory()->create(['status' => ItemStatus::DIPINJAM]);

    expect(InventoryItem::query()->available()->count())->toBe(1);
});

it('reports whether it is on loan', function () {
    $item = InventoryItem::factory()->create(['status' => ItemStatus::DIPINJAM]);

    expect($item->isOnLoan())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Inventory/InventoryItemModelTest.php
```

Expected: FAIL because the enums, migration, and model do not exist.

- [ ] **Step 3: Create the condition enum**

Create `app/Enums/ItemCondition.php`:

```php
<?php

namespace App\Enums;

enum ItemCondition: string
{
    case BAIK = 'baik';
    case RUSAK_RINGAN = 'rusak_ringan';
    case RUSAK_BERAT = 'rusak_berat';

    public function label(): string
    {
        return match ($this) {
            self::BAIK => 'Baik',
            self::RUSAK_RINGAN => 'Rusak Ringan',
            self::RUSAK_BERAT => 'Rusak Berat',
        };
    }
}
```

- [ ] **Step 4: Create the status enum**

Create `app/Enums/ItemStatus.php`:

```php
<?php

namespace App\Enums;

enum ItemStatus: string
{
    case TERSEDIA = 'tersedia';
    case DIPINJAM = 'dipinjam';
    case TIDAK_AKTIF = 'tidak_aktif';

    public function label(): string
    {
        return match ($this) {
            self::TERSEDIA => 'Tersedia',
            self::DIPINJAM => 'Dipinjam',
            self::TIDAK_AKTIF => 'Tidak Aktif',
        };
    }
}
```

- [ ] **Step 5: Create the migration**

```bash
php artisan make:migration create_inventory_items_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('inventory_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('condition')->default('baik');
        $table->string('status')->default('tersedia');
        $table->string('location')->nullable();
        $table->string('holder')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index('status');
    });
}

public function down(): void
{
    Schema::dropIfExists('inventory_items');
}
```

- [ ] **Step 6: Create the model**

Create `app/Models/InventoryItem.php`:

```php
<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'condition',
        'status',
        'location',
        'holder',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'status' => ItemStatus::class,
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::TERSEDIA->value);
    }

    public function isOnLoan(): bool
    {
        return $this->status === ItemStatus::DIPINJAM;
    }
}
```

- [ ] **Step 7: Create the factory**

Create `database/factories/InventoryItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Kursi Lipat', 'Tenda', 'Sound System', 'Genset', 'Meja Panjang']).' '.fake()->numberBetween(1, 20),
            'condition' => ItemCondition::BAIK,
            'status' => ItemStatus::TERSEDIA,
            'location' => 'Sekretariat RT',
            'holder' => null,
            'notes' => null,
        ];
    }

    public function onLoan(): static
    {
        return $this->state(fn () => [
            'status' => ItemStatus::DIPINJAM,
            'holder' => fake()->name(),
        ]);
    }
}
```

- [ ] **Step 8: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Inventory/InventoryItemModelTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Enums/ItemCondition.php app/Enums/ItemStatus.php app/Models/InventoryItem.php database/migrations database/factories/InventoryItemFactory.php tests/Feature/Inventory/InventoryItemModelTest.php
git commit -m "feat: add inventory item model"
```

## Task 2: Build the Inventory Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/inventory/index.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Inventory/InventoryManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Inventory/InventoryManagementTest.php`:

```php
<?php

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from inventory management', function () {
    $this->get('/dashboard/inventaris')->assertRedirect('/login');
});

it('lists items for pengurus', function () {
    InventoryItem::factory()->create(['name' => 'Tenda Biru']);

    $this->actingAs($this->admin)
        ->get('/dashboard/inventaris')
        ->assertOk()
        ->assertSee('Tenda Biru');
});

it('creates an item and audits it', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->set('name', 'Sound System')
        ->set('condition', 'baik')
        ->set('location', 'Gudang RT')
        ->call('save')
        ->assertHasNoErrors();

    expect(InventoryItem::query()->where('name', 'Sound System')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'inventory.created')->exists())->toBeTrue();
});

it('lends an item and records the holder', function () {
    $item = InventoryItem::factory()->create(['status' => ItemStatus::TERSEDIA]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')
        ->call('startLend', $item->id)
        ->set('holder', 'Pak Budi')
        ->call('lend')
        ->assertHasNoErrors();

    expect($item->fresh()->status)->toBe(ItemStatus::DIPINJAM);
    expect($item->fresh()->holder)->toBe('Pak Budi');
    expect(AuditLog::query()->where('action', 'inventory.lent')->exists())->toBeTrue();
});

it('returns a lent item back to available', function () {
    $item = InventoryItem::factory()->onLoan()->create();

    $this->actingAs($this->admin);

    Volt::test('dashboard.inventory.index')->call('returnItem', $item->id);

    expect($item->fresh()->status)->toBe(ItemStatus::TERSEDIA);
    expect($item->fresh()->holder)->toBeNull();
    expect(AuditLog::query()->where('action', 'inventory.returned')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Inventory/InventoryManagementTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/inventaris', 'dashboard.inventory.index')->name('inventory.index');
```

- [ ] **Step 4: Create the management page**

Create `resources/views/livewire/dashboard/inventory/index.blade.php`:

```blade
<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed};

state([
    'editingId' => null,
    'name' => '',
    'condition' => 'baik',
    'location' => '',
    'notes' => '',
    'lendId' => null,
    'holder' => '',
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'condition' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
    'location' => ['nullable', 'string', 'max:255'],
    'notes' => ['nullable', 'string', 'max:1000'],
]);

$items = computed(fn () => InventoryItem::query()->orderBy('name')->get());
$conditions = computed(fn () => ItemCondition::cases());

$edit = function (int $id) {
    $item = InventoryItem::findOrFail($id);
    $this->editingId = $item->id;
    $this->name = $item->name;
    $this->condition = $item->condition->value;
    $this->location = $item->location ?? '';
    $this->notes = $item->notes ?? '';
};

$resetForm = fn () => $this->reset('editingId', 'name', 'condition', 'location', 'notes');

$save = function () {
    $data = $this->validate();

    if ($this->editingId) {
        $item = InventoryItem::findOrFail($this->editingId);
        $item->update($data);
        Audit::record(auth()->user(), 'inventory.updated', 'inventory_item', $item->id, ['name' => $item->name]);
    } else {
        $item = InventoryItem::create($data);
        Audit::record(auth()->user(), 'inventory.created', 'inventory_item', $item->id, ['name' => $item->name]);
    }

    $this->resetForm();
};

$startLend = function (int $id) {
    $this->lendId = $id;
    $this->holder = '';
};

$lend = function () {
    $this->validate(['holder' => ['required', 'string', 'max:255']]);

    $item = InventoryItem::findOrFail($this->lendId);
    $item->update(['status' => ItemStatus::DIPINJAM, 'holder' => $this->holder]);
    Audit::record(auth()->user(), 'inventory.lent', 'inventory_item', $item->id, ['holder' => $this->holder]);

    $this->reset('lendId', 'holder');
};

$returnItem = function (int $id) {
    $item = InventoryItem::findOrFail($id);
    $item->update(['status' => ItemStatus::TERSEDIA, 'holder' => null]);
    Audit::record(auth()->user(), 'inventory.returned', 'inventory_item', $item->id, []);
};

$toggleActive = function (int $id) {
    $item = InventoryItem::findOrFail($id);
    $status = $item->status === ItemStatus::TIDAK_AKTIF ? ItemStatus::TERSEDIA : ItemStatus::TIDAK_AKTIF;
    $item->update(['status' => $status, 'holder' => null]);
    Audit::record(auth()->user(), 'inventory.status_toggled', 'inventory_item', $item->id, ['status' => $status->value]);
};

?>

<x-layouts.app title="Inventaris">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Inventaris RT</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-5">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nama Barang</label>
                <input wire:model="name" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Kondisi</label>
                <select wire:model="condition" class="mt-1 w-full rounded-lg border-slate-300">
                    @foreach ($this->conditions as $condition)
                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Lokasi</label>
                <input wire:model="location" type="text" class="mt-1 w-full rounded-lg border-slate-300">
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
                    {{ $editingId ? 'Perbarui' : 'Tambah' }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Barang</th>
                        <th class="px-4 py-2">Kondisi</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Lokasi/Peminjam</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->name }}</td>
                            <td class="px-4 py-2">{{ $item->condition->label() }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs
                                    @class([
                                        'bg-emerald-100 text-emerald-700' => $item->status === \App\Enums\ItemStatus::TERSEDIA,
                                        'bg-amber-100 text-amber-700' => $item->status === \App\Enums\ItemStatus::DIPINJAM,
                                        'bg-slate-100 text-slate-500' => $item->status === \App\Enums\ItemStatus::TIDAK_AKTIF,
                                    ])">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $item->isOnLoan() ? $item->holder : $item->location }}</td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="edit({{ $item->id }})" class="text-slate-600 hover:underline">Edit</button>
                                @if ($item->isOnLoan())
                                    <button wire:click="returnItem({{ $item->id }})" class="ml-3 text-emerald-700 hover:underline">Kembalikan</button>
                                @elseif ($item->status === \App\Enums\ItemStatus::TERSEDIA)
                                    <button wire:click="startLend({{ $item->id }})" class="ml-3 text-emerald-700 hover:underline">Pinjamkan</button>
                                @endif
                                <button wire:click="toggleActive({{ $item->id }})" class="ml-3 text-slate-600 hover:underline">
                                    {{ $item->status === \App\Enums\ItemStatus::TIDAK_AKTIF ? 'Aktifkan' : 'Nonaktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($lendId)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-emerald-200">
                <h2 class="font-medium text-slate-900">Pinjamkan Barang</h2>
                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-slate-700">Peminjam</label>
                        <input wire:model="holder" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                        @error('holder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="lend" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Pinjamkan</button>
                    <button wire:click="$set('lendId', null)" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Add the nav link**

In `resources/views/components/layouts/app.blade.php` nav, add:

```blade
<a href="{{ route('inventory.index') }}" class="text-slate-600 hover:text-slate-900">Inventaris</a>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Inventory/InventoryManagementTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/inventory resources/views/components/layouts/app.blade.php tests/Feature/Inventory/InventoryManagementTest.php
git commit -m "feat: add inventaris management dashboard"
```

## Final Verification

- [ ] Run all checks:

```bash
php artisan test
npm run build
```

Expected: all tests pass and assets build.

- [ ] Manual smoke test:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

- Login as pengurus and open `Inventaris`.
- Add an item, confirm it appears as Tersedia/Baik.
- Pinjamkan the item to a holder, confirm the status flips to Dipinjam and the holder shows.
- Kembalikan the item, confirm it returns to Tersedia with the holder cleared.
- Nonaktifkan an item and confirm the Tidak Aktif status.
- Confirm `audit_logs` has `inventory.created`, `inventory.lent`, and `inventory.returned` entries.
