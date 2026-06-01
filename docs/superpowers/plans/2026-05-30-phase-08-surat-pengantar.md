# Phase 08 Surat Pengantar Sederhana Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the simple letter-request module. Warga submit a surat pengantar request (jenis surat + keperluan) using their registered phone number. Pengurus review and move each request through an approval workflow (diajukan, disetujui, ditolak, selesai) with notes. Public submission requires a registered active phone; pengurus management is login-gated and audited.

**Architecture:** Continue the single Laravel 12 application. One model: `LetterRequest` (phone, resident, letter type, purpose, status, pengurus notes). Submission reuses the Phase 03 `App\Services\ResidentLookup` for phone validation and is rate limited like other open write endpoints. Letter types are a small fixed enum so warga pick from a known list, and request status is a typed enum surfaced on a pengurus dashboard that audits every status change through `App\Support\Audit`. This phase mirrors the Phase 07 laporan structure closely; the difference is the domain fields (letter type/purpose) and the approval-oriented status set.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 02 (`Resident`, `PhoneNumber`), and Phase 03 (`ResidentLookup`, `x-layouts.public`, `x-portal.phone-field`, rate-limit pattern).

---

## Prerequisites

- Phases 01–03 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\Resident`, `App\Services\ResidentLookup`, `App\Support\PhoneNumber`, `x-layouts.app`, `x-layouts.public`, `x-portal.phone-field`, and the `portal.home` service grid all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `app/Enums/LetterType.php` (`domisili`, `usaha`, `tidak_mampu`, `pengantar_ktp`, `lainnya`).
- Create: `app/Enums/LetterStatus.php` (`diajukan`, `disetujui`, `ditolak`, `selesai`).
- Create: `database/migrations/*_create_letter_requests_table.php`.
- Create: `app/Models/LetterRequest.php`.
- Create: `database/factories/LetterRequestFactory.php`.
- Modify: `routes/web.php` (public surat route, dashboard surat route).
- Create: `resources/views/livewire/portal/letter.blade.php`.
- Create: `resources/views/livewire/dashboard/letters/index.blade.php`.
- Modify: `resources/views/livewire/portal/home.blade.php` (add Surat Pengantar entry).
- Modify: `resources/views/components/layouts/app.blade.php` (add Surat nav link).
- Test: `tests/Feature/Letters/LetterRequestModelTest.php`.
- Test: `tests/Feature/Letters/PublicLetterSubmitTest.php`.
- Test: `tests/Feature/Letters/LetterManagementTest.php`.

## Task 1: Create the Letter Request Model

**Files:**

- Create: `app/Enums/LetterType.php`
- Create: `app/Enums/LetterStatus.php`
- Create: `database/migrations/*_create_letter_requests_table.php`
- Create: `app/Models/LetterRequest.php`
- Create: `database/factories/LetterRequestFactory.php`
- Test: `tests/Feature/Letters/LetterRequestModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Letters/LetterRequestModelTest.php`:

```php
<?php

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Resident;

it('casts type and status to enums', function () {
    $letter = LetterRequest::factory()->create([
        'type' => LetterType::DOMISILI,
        'status' => LetterStatus::DIAJUKAN,
    ]);

    expect($letter->fresh()->type)->toBe(LetterType::DOMISILI);
    expect($letter->fresh()->status)->toBe(LetterStatus::DIAJUKAN);
});

it('normalizes the phone on save', function () {
    $letter = LetterRequest::factory()->create(['phone' => '0812-3456-7890']);

    expect($letter->fresh()->phone)->toBe('81234567890');
});

it('optionally links to a resident', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $letter = LetterRequest::factory()->create(['resident_id' => $resident->id]);

    expect($letter->resident->is($resident))->toBeTrue();
});

it('scopes pending requests', function () {
    LetterRequest::factory()->create(['status' => LetterStatus::DIAJUKAN]);
    LetterRequest::factory()->create(['status' => LetterStatus::DISETUJUI]);
    LetterRequest::factory()->create(['status' => LetterStatus::SELESAI]);

    expect(LetterRequest::query()->pending()->count())->toBe(2);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Letters/LetterRequestModelTest.php
```

Expected: FAIL because the enums, migration, and model do not exist.

- [ ] **Step 3: Create the letter type enum**

Create `app/Enums/LetterType.php`:

```php
<?php

namespace App\Enums;

enum LetterType: string
{
    case DOMISILI = 'domisili';
    case USAHA = 'usaha';
    case TIDAK_MAMPU = 'tidak_mampu';
    case PENGANTAR_KTP = 'pengantar_ktp';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::DOMISILI => 'Surat Keterangan Domisili',
            self::USAHA => 'Surat Keterangan Usaha',
            self::TIDAK_MAMPU => 'Surat Keterangan Tidak Mampu',
            self::PENGANTAR_KTP => 'Pengantar KTP',
            self::LAINNYA => 'Lainnya',
        };
    }
}
```

- [ ] **Step 4: Create the letter status enum**

Create `app/Enums/LetterStatus.php`:

```php
<?php

namespace App\Enums;

enum LetterStatus: string
{
    case DIAJUKAN = 'diajukan';
    case DISETUJUI = 'disetujui';
    case DITOLAK = 'ditolak';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::DIAJUKAN => 'Diajukan',
            self::DISETUJUI => 'Disetujui',
            self::DITOLAK => 'Ditolak',
            self::SELESAI => 'Selesai',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::DIAJUKAN, self::DISETUJUI], true);
    }
}
```

- [ ] **Step 5: Create the migration**

```bash
php artisan make:migration create_letter_requests_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('letter_requests', function (Blueprint $table) {
        $table->id();
        $table->string('phone');
        $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
        $table->string('type');
        $table->text('purpose');
        $table->string('status')->default('diajukan');
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index('status');
        $table->index('phone');
    });
}

public function down(): void
{
    Schema::dropIfExists('letter_requests');
}
```

- [ ] **Step 6: Create the model**

Create `app/Models/LetterRequest.php`:

```php
<?php

namespace App\Models;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'resident_id',
        'type',
        'purpose',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => LetterType::class,
            'status' => LetterStatus::class,
        ];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [LetterStatus::DIAJUKAN->value, LetterStatus::DISETUJUI->value]);
    }
}
```

- [ ] **Step 7: Create the factory**

Create `database/factories/LetterRequestFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\LetterRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LetterRequestFactory extends Factory
{
    protected $model = LetterRequest::class;

    public function definition(): array
    {
        return [
            'phone' => '8'.fake()->unique()->numerify('##########'),
            'type' => fake()->randomElement(LetterType::cases()),
            'purpose' => fake()->sentence(),
            'status' => LetterStatus::DIAJUKAN,
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 8: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Letters/LetterRequestModelTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Enums/LetterType.php app/Enums/LetterStatus.php app/Models/LetterRequest.php database/migrations database/factories/LetterRequestFactory.php tests/Feature/Letters/LetterRequestModelTest.php
git commit -m "feat: add letter request model"
```

## Task 2: Build the Public Surat Submission Page

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/portal/letter.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Letters/PublicLetterSubmitTest.php`

- [ ] **Step 1: Write failing submission tests**

Create `tests/Feature/Letters/PublicLetterSubmitTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-letter:127.0.0.1');
    $this->household = Household::factory()->create();
});

it('serves the surat page without login', function () {
    $this->get('/surat')->assertOk()->assertSee('Surat Pengantar');
});

it('stores a request for a registered active phone', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.letter')
        ->set('phone', '0812-3456-7890')
        ->set('type', 'domisili')
        ->set('purpose', 'Untuk keperluan administrasi bank.')
        ->call('submit')
        ->assertSee('Pengajuan terkirim');

    $letter = LetterRequest::query()->first();
    expect($letter->phone)->toBe('81234567890');
    expect($letter->resident_id)->toBe($resident->id);
    expect($letter->type->value)->toBe('domisili');
});

it('rejects a request from an unregistered phone', function () {
    Volt::test('portal.letter')
        ->set('phone', '0899-0000-0000')
        ->set('type', 'domisili')
        ->set('purpose', 'Test keperluan.')
        ->call('submit')
        ->assertSee('belum terdaftar');

    expect(LetterRequest::query()->count())->toBe(0);
});

it('validates required fields', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    Volt::test('portal.letter')
        ->set('phone', '81234567890')
        ->set('type', '')
        ->set('purpose', '')
        ->call('submit')
        ->assertHasErrors(['type', 'purpose']);
});

it('rate limits repeated submissions', function () {
    $component = Volt::test('portal.letter');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->set('type', 'domisili')->set('purpose', 'Y keperluan')->call('submit');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Letters/PublicLetterSubmitTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the public route**

In `routes/web.php`, with the other public portal routes:

```php
Volt::route('/surat', 'portal.letter')->name('portal.letter');
```

- [ ] **Step 4: Create the surat page**

Create `resources/views/livewire/portal/letter.blade.php`:

```blade
<?php

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\LetterRequest;
use App\Services\ResidentLookup;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules, computed};

state([
    'phone' => '',
    'type' => '',
    'purpose' => '',
    'done' => false,
    'feedback' => null,
]);

rules([
    'phone' => ['required', 'string', 'max:30'],
    'type' => ['required', 'in:domisili,usaha,tidak_mampu,pengantar_ktp,lainnya'],
    'purpose' => ['required', 'string', 'min:5', 'max:2000'],
]);

$types = computed(fn () => LetterType::cases());

$submit = function (ResidentLookup $lookup) {
    $this->validate();

    $key = 'portal-letter:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->done = false;
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $lookup->resolve($this->phone);

    if (! $result->found()) {
        $this->done = false;
        $this->feedback = $result->message;

        return;
    }

    LetterRequest::create([
        'phone' => $this->phone,
        'resident_id' => $result->resident->id,
        'type' => $this->type,
        'purpose' => $this->purpose,
        'status' => LetterStatus::DIAJUKAN,
    ]);

    $this->reset('phone', 'type', 'purpose');
    $this->done = true;
    $this->feedback = null;
};

?>

<x-layouts.public title="Surat Pengantar">
    <div class="space-y-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Surat Pengantar</h1>
            <p class="mt-1 text-sm text-slate-600">Ajukan surat pengantar RT. Gunakan nomor HP yang terdaftar.</p>

            <form wire:submit="submit" class="mt-4 space-y-4">
                <x-portal.phone-field model="phone" />
                <div>
                    <label class="block text-sm font-medium text-slate-700">Jenis Surat</label>
                    <select wire:model="type" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">Pilih jenis surat</option>
                        @foreach ($this->types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Keperluan</label>
                    <textarea wire:model="purpose" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    @error('purpose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Ajukan Surat</button>
            </form>
        </div>

        @if ($done)
            <div class="rounded-2xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-200">
                <p class="text-lg font-semibold text-emerald-800">Pengajuan terkirim</p>
                <p class="mt-1 text-sm text-emerald-700">Pengurus akan memproses pengajuan surat Anda.</p>
            </div>
        @elseif ($feedback)
            <div class="rounded-2xl bg-amber-50 p-5 text-center ring-1 ring-amber-200">
                <p class="text-sm text-amber-700">{{ $feedback }}</p>
            </div>
        @endif

        <div class="text-center">
            <a href="{{ route('portal.home') }}" class="text-sm text-emerald-700 hover:underline">Kembali ke portal</a>
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 5: Enable the portal home entry**

In `resources/views/livewire/portal/home.blade.php`, add a Surat Pengantar entry:

```php
['label' => 'Surat Pengantar', 'route' => 'portal.letter', 'desc' => 'Ajukan surat pengantar RT.', 'ready' => true],
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Letters/PublicLetterSubmitTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/portal/letter.blade.php resources/views/livewire/portal/home.blade.php tests/Feature/Letters/PublicLetterSubmitTest.php
git commit -m "feat: add public surat pengantar submission"
```

## Task 3: Build the Letter Request Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/letters/index.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Letters/LetterManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Letters/LetterManagementTest.php`:

```php
<?php

use App\Enums\LetterStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\LetterRequest;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from letter management', function () {
    $this->get('/dashboard/surat')->assertRedirect('/login');
});

it('lists submitted letter requests', function () {
    LetterRequest::factory()->create(['purpose' => 'Buka rekening bank']);

    $this->actingAs($this->admin)
        ->get('/dashboard/surat')
        ->assertOk()
        ->assertSee('Buka rekening bank');
});

it('updates a request status with notes and audits it', function () {
    $letter = LetterRequest::factory()->create(['status' => LetterStatus::DIAJUKAN]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.letters.index')
        ->call('startUpdate', $letter->id)
        ->set('status', LetterStatus::DISETUJUI->value)
        ->set('notes', 'Diambil di rumah Ketua RT.')
        ->call('saveUpdate')
        ->assertHasNoErrors();

    expect($letter->fresh()->status)->toBe(LetterStatus::DISETUJUI);
    expect($letter->fresh()->notes)->toBe('Diambil di rumah Ketua RT.');
    expect(AuditLog::query()->where('action', 'letter.status_changed')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Letters/LetterManagementTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/surat', 'dashboard.letters.index')->name('letters.index');
```

- [ ] **Step 4: Create the management page**

Create `resources/views/livewire/dashboard/letters/index.blade.php`:

```blade
<?php

use App\Enums\LetterStatus;
use App\Models\LetterRequest;
use App\Support\Audit;
use function Livewire\Volt\{state, computed, rules};

state(['updateId' => null, 'status' => '', 'notes' => '', 'filter' => 'pending']);

rules([
    'status' => ['required', 'in:diajukan,disetujui,ditolak,selesai'],
    'notes' => ['nullable', 'string', 'max:2000'],
]);

$letters = computed(function () {
    $query = LetterRequest::query()->with('resident')->latest();

    if ($this->filter === 'pending') {
        $query->pending();
    }

    return $query->get();
});

$statuses = computed(fn () => LetterStatus::cases());

$startUpdate = function (int $id) {
    $letter = LetterRequest::findOrFail($id);
    $this->updateId = $letter->id;
    $this->status = $letter->status->value;
    $this->notes = $letter->notes ?? '';
};

$saveUpdate = function () {
    $this->validate();

    $letter = LetterRequest::findOrFail($this->updateId);
    $from = $letter->status->value;
    $letter->update(['status' => $this->status, 'notes' => $this->notes]);

    Audit::record(auth()->user(), 'letter.status_changed', 'letter_request', $letter->id, [
        'from' => $from,
        'to' => $this->status,
    ]);

    $this->reset('updateId', 'status', 'notes');
};

?>

<x-layouts.app title="Surat Pengantar">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">Surat Pengantar</h1>
            <select wire:model.live="filter" class="rounded-lg border-slate-300 text-sm">
                <option value="pending">Perlu diproses</option>
                <option value="all">Semua</option>
            </select>
        </div>

        <div class="space-y-3">
            @forelse ($this->letters as $letter)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-medium text-slate-900">{{ $letter->type->label() }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $letter->purpose }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ $letter->resident?->name ?? 'Warga' }} · {{ $letter->phone }} · {{ $letter->created_at->format('d/m/Y H:i') }}
                            </p>
                            @if ($letter->notes)
                                <p class="mt-2 rounded-lg bg-slate-50 p-2 text-sm text-slate-600">Catatan: {{ $letter->notes }}</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $letter->status->label() }}</span>
                    </div>

                    @if ($updateId === $letter->id)
                        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            <div class="flex flex-wrap items-end gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Status</label>
                                    <select wire:model="status" class="mt-1 rounded-lg border-slate-300">
                                        @foreach ($this->statuses as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-slate-700">Catatan</label>
                                    <input wire:model="notes" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                                </div>
                            </div>
                            @error('status') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button wire:click="saveUpdate" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Simpan</button>
                                <button wire:click="$set('updateId', null)" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:text-slate-900">Batal</button>
                            </div>
                        </div>
                    @else
                        <button wire:click="startUpdate({{ $letter->id }})" class="mt-3 text-sm text-emerald-700 hover:underline">Proses</button>
                    @endif
                </div>
            @empty
                <p class="text-slate-500">Tidak ada pengajuan surat.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Add the nav link**

In `resources/views/components/layouts/app.blade.php` nav, add:

```blade
<a href="{{ route('letters.index') }}" class="text-slate-600 hover:text-slate-900">Surat</a>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Letters/LetterManagementTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/letters resources/views/components/layouts/app.blade.php tests/Feature/Letters/LetterManagementTest.php
git commit -m "feat: add surat pengantar management dashboard"
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

- Open `/surat` (no login), submit a request with a seeded resident's phone, and confirm "Pengajuan terkirim".
- Submit with an unregistered phone and confirm the "belum terdaftar" message.
- Login as pengurus, open `Surat`, confirm the request appears, change its status to Disetujui with a note, and verify the "Perlu diproses" filter behavior.
- Confirm `audit_logs` has a `letter.status_changed` entry.
