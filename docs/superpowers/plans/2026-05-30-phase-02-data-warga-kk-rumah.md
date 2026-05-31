# Phase 02 Data Warga, KK, dan Rumah Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build the core master data module for Smart RT: rumah/KK (households), warga (residents), and unique QR tokens per rumah. Provide Admin RT dashboard CRUD for households and residents, enforce unique phone numbers for active warga, render the per-rumah QR, and audit every mutation.

**Architecture:** Continue the single Laravel 12 application from Phase 01. Add two domain models (`Household`, `Resident`) with a one-to-many relationship (one rumah/KK has many warga). Households own a unique `qr_token` generated on create that encodes only the token (no PII), per the design. All write actions live under the authenticated `pengurus` dashboard and write to the Phase 01 `audit_logs` table through `App\Support\Audit`. QR images are rendered as inline SVG so no `imagick`/`gd` extension is required.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest, direct `bacon/bacon-qr-code` SVG rendering through `App\Support\QrCode`. Builds on Phase 01 (`UserRole`, `EnsurePengurus` middleware, `Audit` helper, `x-layouts.app`).

---

## Prerequisites

- Phase 01 is complete: Laravel app boots, `auth` + `pengurus` middleware exist, `App\Support\Audit::record()` works, and `x-layouts.app` renders.
- `ddev artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `database/migrations/*_create_households_table.php`.
- Create: `database/migrations/*_create_residents_table.php`.
- Create: `app/Models/Household.php`.
- Create: `app/Models/Resident.php`.
- Create: `database/factories/HouseholdFactory.php`.
- Create: `database/factories/ResidentFactory.php`.
- Create: `app/Rules/UniqueActivePhone.php`.
- Create: `app/Support/PhoneNumber.php` (normalizer helper).
- Create: `routes/web.php` additions for household and resident management.
- Create: `resources/views/livewire/households/index.blade.php` (list + create/edit/toggle).
- Create: `resources/views/livewire/households/qr.blade.php` (QR view per rumah).
- Create: `resources/views/livewire/residents/index.blade.php` (list + create/edit/toggle).
- Modify: `resources/views/components/layouts/app.blade.php` (add nav links to Rumah and Warga).
- Modify: `resources/views/livewire/dashboard/index.blade.php` (summary counts).
- Create: `database/seeders/DemoDataSeeder.php` and register in `DatabaseSeeder`.
- Test: `tests/Feature/Households/HouseholdModelTest.php`.
- Test: `tests/Feature/Households/HouseholdManagementTest.php`.
- Test: `tests/Feature/Residents/ResidentModelTest.php`.
- Test: `tests/Feature/Residents/ResidentManagementTest.php`.

## Task 1: Create Household Model, Migration, and QR Token

**Files:**

- Create: `database/migrations/*_create_households_table.php`
- Create: `app/Models/Household.php`
- Create: `database/factories/HouseholdFactory.php`
- Test: `tests/Feature/Households/HouseholdModelTest.php`

- [x] **Step 1: Write failing household model tests**

Create `tests/Feature/Households/HouseholdModelTest.php`:

```php
<?php

use App\Models\Household;

it('generates a unique qr token on create', function () {
    $a = Household::factory()->create();
    $b = Household::factory()->create();

    expect($a->qr_token)->not->toBeNull();
    expect($b->qr_token)->not->toBeNull();
    expect($a->qr_token)->not->toBe($b->qr_token);
});

it('does not overwrite an explicitly provided qr token', function () {
    $household = Household::factory()->create(['qr_token' => 'fixed-token-123']);

    expect($household->fresh()->qr_token)->toBe('fixed-token-123');
});

it('casts is_active to boolean and defaults active', function () {
    $household = Household::factory()->create();

    expect($household->is_active)->toBeTrue();
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Households/HouseholdModelTest.php
```

Expected: FAIL because `App\Models\Household` does not exist.

- [x] **Step 3: Create migration**

```bash
ddev artisan make:migration create_households_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('households', function (Blueprint $table) {
        $table->id();
        $table->string('house_number');
        $table->string('address')->nullable();
        $table->string('head_name');
        $table->string('qr_token')->unique();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index('is_active');
    });
}

public function down(): void
{
    Schema::dropIfExists('households');
}
```

- [x] **Step 4: Create model with auto QR token**

Create `app/Models/Household.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'house_number',
        'address',
        'head_name',
        'qr_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Household $household) {
            if (blank($household->qr_token)) {
                $household->qr_token = (string) Str::uuid();
            }
        });
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function activeResidents(): HasMany
    {
        return $this->residents()->where('is_active', true);
    }
}
```

- [x] **Step 5: Create factory**

Create `database/factories/HouseholdFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'house_number' => 'No. '.fake()->unique()->numberBetween(1, 200),
            'address' => fake()->streetAddress(),
            'head_name' => fake()->name(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

- [x] **Step 6: Run tests**

```bash
ddev artisan migrate:fresh --env=testing
ddev artisan test tests/Feature/Households/HouseholdModelTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
git add app/Models/Household.php database/migrations database/factories/HouseholdFactory.php tests/Feature/Households/HouseholdModelTest.php
git commit -m "feat: add household model with unique qr token"
```

## Task 2: Create Resident Model with Active-Phone Uniqueness

**Files:**

- Create: `database/migrations/*_create_residents_table.php`
- Create: `app/Models/Resident.php`
- Create: `database/factories/ResidentFactory.php`
- Create: `app/Support/PhoneNumber.php`
- Create: `app/Rules/UniqueActivePhone.php`
- Test: `tests/Feature/Residents/ResidentModelTest.php`

- [x] **Step 1: Write failing resident tests**

Create `tests/Feature/Residents/ResidentModelTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;

it('normalizes phone numbers to a canonical form', function () {
    expect(PhoneNumber::normalize('0812-3456-7890'))->toBe('81234567890');
    expect(PhoneNumber::normalize('+62 812 3456 7890'))->toBe('81234567890');
});

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->for($household)->create();

    expect($resident->household->is($household))->toBeTrue();
});

it('rejects a duplicate phone for active residents', function () {
    $household = Household::factory()->create();
    Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => true]);

    $validator = Validator::make(
        ['phone' => '0812-3456-7890'],
        ['phone' => [new UniqueActivePhone()]],
    );

    expect($validator->fails())->toBeTrue();
});

it('allows reusing a phone held only by an inactive resident', function () {
    $household = Household::factory()->create();
    Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => false]);

    $validator = Validator::make(
        ['phone' => '81234567890'],
        ['phone' => [new UniqueActivePhone()]],
    );

    expect($validator->fails())->toBeFalse();
});

it('ignores a given resident id when updating', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => true]);

    $validator = Validator::make(
        ['phone' => '81234567890'],
        ['phone' => [new UniqueActivePhone(ignoreResidentId: $resident->id)]],
    );

    expect($validator->fails())->toBeFalse();
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Residents/ResidentModelTest.php
```

Expected: FAIL because resident classes, helper, and rule do not exist.

- [x] **Step 3: Create migration**

```bash
ddev artisan make:migration create_residents_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('residents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('household_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('phone');
        $table->boolean('is_active')->default(true);
        $table->text('ronda_notes')->nullable();
        $table->timestamps();

        $table->index('phone');
        $table->index(['is_active', 'phone']);
    });
}

public function down(): void
{
    Schema::dropIfExists('residents');
}
```

> Note: The design requires phone uniqueness only for active warga, so uniqueness is enforced in the application layer (`UniqueActivePhone`) rather than a DB unique index, which cannot express the active-only condition portably.

- [x] **Step 4: Create phone normalizer**

Create `app/Support/PhoneNumber.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Str;

class PhoneNumber
{
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (Str::startsWith($digits, '62')) {
            $digits = substr($digits, 2);
        }

        return ltrim($digits, '0');
    }
}
```

- [x] **Step 5: Create resident model**

Create `app/Models/Resident.php`:

```php
<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'name',
        'phone',
        'is_active',
        'ronda_notes',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
```

- [x] **Step 6: Create active-phone rule**

Create `app/Rules/UniqueActivePhone.php`:

```php
<?php

namespace App\Rules;

use App\Models\Resident;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueActivePhone implements ValidationRule
{
    public function __construct(
        protected ?int $ignoreResidentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = PhoneNumber::normalize(is_string($value) ? $value : null);

        $exists = Resident::query()
            ->where('is_active', true)
            ->where('phone', $normalized)
            ->when($this->ignoreResidentId, fn ($query) => $query->whereKeyNot($this->ignoreResidentId))
            ->exists();

        if ($exists) {
            $fail('Nomor HP sudah terdaftar untuk warga aktif lain.');
        }
    }
}
```

- [x] **Step 7: Create factory**

Create `database/factories/ResidentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->name(),
            'phone' => '8'.fake()->unique()->numerify('##########'),
            'is_active' => true,
            'ronda_notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

- [x] **Step 8: Run tests**

```bash
ddev artisan migrate:fresh --env=testing
ddev artisan test tests/Feature/Residents/ResidentModelTest.php
```

Expected: PASS.

- [x] **Step 9: Commit**

```bash
git add app/Models/Resident.php app/Support/PhoneNumber.php app/Rules/UniqueActivePhone.php database/migrations database/factories/ResidentFactory.php tests/Feature/Residents/ResidentModelTest.php
git commit -m "feat: add resident model with active phone uniqueness"
```

## Task 3: Build Household Management Dashboard and QR View

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/households/index.blade.php`
- Create: `resources/views/livewire/households/qr.blade.php`
- Test: `tests/Feature/Households/HouseholdManagementTest.php`

- [x] **Step 1: Install QR package**

```bash
ddev composer require bacon/bacon-qr-code:^3.1
```

Expected: package installed; SVG rendering works without `imagick`/`gd` through direct `App\Support\QrCode` integration.

- [x] **Step 2: Write failing management tests**

Create `tests/Feature/Households/HouseholdManagementTest.php`:

```php
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

it('creates a household and writes an audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('households.index')
        ->set('house_number', 'No. 12')
        ->set('address', 'Jl. Mawar')
        ->set('head_name', 'Siti')
        ->call('save')
        ->assertHasNoErrors();

    $household = Household::query()->where('head_name', 'Siti')->first();
    expect($household)->not->toBeNull();
    expect($household->qr_token)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'household.created')->exists())->toBeTrue();
});

it('renders a qr svg for a household', function () {
    $household = Household::factory()->create();

    $this->actingAs($this->admin)
        ->get("/dashboard/rumah/{$household->id}/qr")
        ->assertOk()
        ->assertSee('<svg', false);
});
```

- [x] **Step 3: Run failing tests**

```bash
ddev artisan test tests/Feature/Households/HouseholdManagementTest.php
```

Expected: FAIL because routes and Volt views are missing.

- [x] **Step 4: Add routes**

In `routes/web.php`, inside the existing `auth` + `pengurus` group:

```php
Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');

    Volt::route('/dashboard/rumah', 'households.index')->name('households.index');
    Volt::route('/dashboard/rumah/{household}/qr', 'households.qr')->name('households.qr');
});
```

- [x] **Step 5: Create household management Volt page**

Create `resources/views/livewire/households/index.blade.php`:

```blade
<?php

use App\Models\Household;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed, mount};

state([
    'editingId' => null,
    'house_number' => '',
    'address' => '',
    'head_name' => '',
]);

rules([
    'house_number' => ['required', 'string', 'max:255'],
    'address' => ['nullable', 'string', 'max:255'],
    'head_name' => ['required', 'string', 'max:255'],
]);

$households = computed(fn () => Household::query()->latest()->get());

$edit = function (int $id) {
    $household = Household::findOrFail($id);
    $this->editingId = $household->id;
    $this->house_number = $household->house_number;
    $this->address = $household->address;
    $this->head_name = $household->head_name;
};

$resetForm = function () {
    $this->reset('editingId', 'house_number', 'address', 'head_name');
};

$save = function () {
    $data = $this->validate();

    if ($this->editingId) {
        $household = Household::findOrFail($this->editingId);
        $household->update($data);
        Audit::record(auth()->user(), 'household.updated', 'household', $household->id, $data);
    } else {
        $household = Household::create($data);
        Audit::record(auth()->user(), 'household.created', 'household', $household->id, $data);
    }

    $this->resetForm();
};

$toggleActive = function (int $id) {
    $household = Household::findOrFail($id);
    $household->update(['is_active' => ! $household->is_active]);
    Audit::record(auth()->user(), 'household.toggled', 'household', $household->id, ['is_active' => $household->is_active]);
};

?>

<x-layouts.app title="Data Rumah/KK">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Data Rumah/KK</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Nomor Rumah</label>
                <input wire:model="house_number" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('house_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Alamat</label>
                <input wire:model="address" type="text" class="mt-1 w-full rounded-lg border-slate-300">
            </div>
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Kepala Keluarga</label>
                <input wire:model="head_name" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('head_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <th class="px-4 py-2">Nomor</th>
                        <th class="px-4 py-2">Kepala Keluarga</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->households as $household)
                        <tr>
                            <td class="px-4 py-2">{{ $household->house_number }}</td>
                            <td class="px-4 py-2">{{ $household->head_name }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $household->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('households.qr', $household) }}" class="text-emerald-700 hover:underline">QR</a>
                                <button wire:click="edit({{ $household->id }})" class="ml-3 text-slate-600 hover:underline">Edit</button>
                                <button wire:click="toggleActive({{ $household->id }})" class="ml-3 text-slate-600 hover:underline">
                                    {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 6: Create QR Volt page**

Create `resources/views/livewire/households/qr.blade.php`:

```blade
<?php

use App\Models\Household;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use function Livewire\Volt\{state, computed, mount};

state(['household' => null]);

mount(function (Household $household) {
    $this->household = $household;
});

$svg = computed(fn () => (string) QrCode::format('svg')->size(280)->generate($this->household->qr_token));

?>

<x-layouts.app title="QR Rumah">
    <div class="mx-auto max-w-md space-y-4 text-center">
        <h1 class="text-xl font-semibold text-slate-900">QR Rumah {{ $household->house_number }}</h1>
        <p class="text-slate-600">{{ $household->head_name }}</p>
        <div class="inline-block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            {!! $this->svg !!}
        </div>
        <p class="text-xs text-slate-400">Token: {{ $household->qr_token }}</p>
        <div>
            <a href="{{ route('households.index') }}" class="text-emerald-700 hover:underline">Kembali</a>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 7: Run tests**

```bash
ddev artisan test tests/Feature/Households/HouseholdManagementTest.php
```

Expected: PASS.

- [x] **Step 8: Commit**

```bash
git add composer.json composer.lock routes/web.php resources/views/livewire/households tests/Feature/Households/HouseholdManagementTest.php
git commit -m "feat: add household management dashboard and qr view"
```

## Task 4: Build Resident Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/residents/index.blade.php`
- Test: `tests/Feature/Residents/ResidentManagementTest.php`

- [x] **Step 1: Write failing management tests**

Create `tests/Feature/Residents/ResidentManagementTest.php`:

```php
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

it('creates a resident with a normalized phone and audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->set('household_id', $this->household->id)
        ->set('name', 'Andi')
        ->set('phone', '0812-3456-7890')
        ->call('save')
        ->assertHasNoErrors();

    $resident = Resident::query()->where('name', 'Andi')->first();
    expect($resident)->not->toBeNull();
    expect($resident->phone)->toBe('81234567890');
    expect(AuditLog::query()->where('action', 'resident.created')->exists())->toBeTrue();
});

it('rejects a duplicate active phone', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->set('household_id', $this->household->id)
        ->set('name', 'Dewi')
        ->set('phone', '081234567890')
        ->call('save')
        ->assertHasErrors('phone');
});

it('allows editing a resident keeping its own phone', function () {
    $resident = Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('residents.index')
        ->call('edit', $resident->id)
        ->set('name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($resident->fresh()->name)->toBe('Nama Baru');
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Residents/ResidentManagementTest.php
```

Expected: FAIL because route and view are missing.

- [x] **Step 3: Add route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/warga', 'residents.index')->name('residents.index');
```

- [x] **Step 4: Create resident management Volt page**

Create `resources/views/livewire/residents/index.blade.php`:

```blade
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\Audit;
use function Livewire\Volt\{state, computed};

state([
    'editingId' => null,
    'household_id' => null,
    'name' => '',
    'phone' => '',
    'ronda_notes' => '',
]);

$residents = computed(fn () => Resident::query()->with('household')->latest()->get());
$households = computed(fn () => Household::query()->where('is_active', true)->orderBy('house_number')->get());

$rulesFor = function (): array {
    return [
        'household_id' => ['required', 'exists:households,id'],
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:30', new UniqueActivePhone(ignoreResidentId: $this->editingId)],
        'ronda_notes' => ['nullable', 'string', 'max:500'],
    ];
};

$edit = function (int $id) {
    $resident = Resident::findOrFail($id);
    $this->editingId = $resident->id;
    $this->household_id = $resident->household_id;
    $this->name = $resident->name;
    $this->phone = $resident->phone;
    $this->ronda_notes = $resident->ronda_notes;
};

$resetForm = function () {
    $this->reset('editingId', 'household_id', 'name', 'phone', 'ronda_notes');
};

$save = function () {
    $data = $this->validate($this->rulesFor());

    if ($this->editingId) {
        $resident = Resident::findOrFail($this->editingId);
        $resident->update($data);
        Audit::record(auth()->user(), 'resident.updated', 'resident', $resident->id, ['name' => $resident->name]);
    } else {
        $resident = Resident::create($data);
        Audit::record(auth()->user(), 'resident.created', 'resident', $resident->id, ['name' => $resident->name]);
    }

    $this->resetForm();
};

$toggleActive = function (int $id) {
    $resident = Resident::findOrFail($id);
    $resident->update(['is_active' => ! $resident->is_active]);
    Audit::record(auth()->user(), 'resident.toggled', 'resident', $resident->id, ['is_active' => $resident->is_active]);
};

?>

<x-layouts.app title="Data Warga">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Data Warga</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">Rumah/KK</label>
                <select wire:model="household_id" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih rumah</option>
                    @foreach ($this->households as $household)
                        <option value="{{ $household->id }}">{{ $household->house_number }} - {{ $household->head_name }}</option>
                    @endforeach
                </select>
                @error('household_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nama</label>
                <input wire:model="name" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nomor HP</label>
                <input wire:model="phone" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Catatan Ronda</label>
                <input wire:model="ronda_notes" type="text" class="mt-1 w-full rounded-lg border-slate-300">
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
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Nomor HP</th>
                        <th class="px-4 py-2">Rumah</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->residents as $resident)
                        <tr>
                            <td class="px-4 py-2">{{ $resident->name }}</td>
                            <td class="px-4 py-2">{{ $resident->phone }}</td>
                            <td class="px-4 py-2">{{ $resident->household?->house_number }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $resident->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="edit({{ $resident->id }})" class="text-slate-600 hover:underline">Edit</button>
                                <button wire:click="toggleActive({{ $resident->id }})" class="ml-3 text-slate-600 hover:underline">
                                    {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 5: Run tests**

```bash
ddev artisan test tests/Feature/Residents/ResidentManagementTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add routes/web.php resources/views/livewire/residents tests/Feature/Residents/ResidentManagementTest.php
git commit -m "feat: add resident management dashboard"
```

## Task 5: Add Navigation, Dashboard Summary, and Demo Seeder

**Files:**

- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `resources/views/livewire/dashboard/index.blade.php`
- Create: `database/seeders/DemoDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [x] **Step 1: Add nav links to app layout**

In `resources/views/components/layouts/app.blade.php`, add navigation links beside the brand in the header:

```blade
<nav class="flex items-center gap-4 text-sm">
    <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Dashboard</a>
    <a href="{{ route('households.index') }}" class="text-slate-600 hover:text-slate-900">Rumah/KK</a>
    <a href="{{ route('residents.index') }}" class="text-slate-600 hover:text-slate-900">Warga</a>
</nav>
```

- [x] **Step 2: Show summary counts on the dashboard**

Replace `resources/views/livewire/dashboard/index.blade.php`:

```blade
<?php

use App\Models\Household;
use App\Models\Resident;
use function Livewire\Volt\{computed};

$householdCount = computed(fn () => Household::query()->where('is_active', true)->count());
$residentCount = computed(fn () => Resident::query()->where('is_active', true)->count());

?>

<x-layouts.app title="Dashboard Pengurus">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard Pengurus</h1>
        <p class="text-slate-600">Selamat datang di Smart RT.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Rumah/KK Aktif</p>
                <p class="mt-1 text-3xl font-semibold text-emerald-700">{{ $this->householdCount }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Warga Aktif</p>
                <p class="mt-1 text-3xl font-semibold text-emerald-700">{{ $this->residentCount }}</p>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 3: Create demo seeder**

Create `database/seeders/DemoDataSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@smartrt.test'],
            ['name' => 'Admin RT', 'password' => Hash::make('password'), 'role' => UserRole::ADMIN_RT],
        );

        User::query()->firstOrCreate(
            ['email' => 'bendahara@smartrt.test'],
            ['name' => 'Bendahara', 'password' => Hash::make('password'), 'role' => UserRole::BENDAHARA],
        );

        Household::factory()
            ->count(5)
            ->create()
            ->each(fn (Household $household) => Resident::factory()->count(2)->for($household)->create());
    }
}
```

- [x] **Step 4: Register seeder**

In `database/seeders/DatabaseSeeder.php`, call the demo seeder:

```php
public function run(): void
{
    $this->call(DemoDataSeeder::class);
}
```

- [x] **Step 5: Verify seeding**

```bash
ddev artisan migrate:fresh --seed
ddev artisan test
```

Expected: seeding succeeds and the whole suite passes.

- [x] **Step 6: Commit**

```bash
git add resources/views database/seeders
git commit -m "feat: add navigation, dashboard summary, and demo seeder"
```

## Final Verification

- [x] Run all checks:

```bash
ddev artisan test
ddev npm run build
```

Expected: all tests pass and assets build.

- [x] Manual smoke test:

```bash
ddev artisan migrate:fresh --seed
ddev launch
```

- Login at the DDEV app URL, for example `https://smart-rt.ddev.site/login`, with `admin@smartrt.test` / `password`.
- Open `Rumah/KK`, add a household, and confirm a QR token is generated.
- Open the household QR page and confirm an SVG renders.
- Open `Warga`, add a resident, confirm the phone normalizes, and confirm a duplicate active phone is rejected.
- Confirm dashboard counts reflect active households and residents.
- Inspect `audit_logs` (via Tinker) and confirm `household.created` and `resident.created` entries exist.
