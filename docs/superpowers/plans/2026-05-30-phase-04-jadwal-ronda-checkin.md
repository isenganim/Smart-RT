# Phase 04 Jadwal Ronda dan Check-in Nomor HP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build the ronda scheduling module and warga self check-in. Pengurus create a ronda schedule per date and assign active warga to it from the dashboard. Warga view the public schedule with no login, and check in for their assigned date using only their registered phone number. Check-in is allowed once per assignment and rejected when the phone is unregistered, inactive, or not scheduled that day.

**Architecture:** Continue the single Laravel 12 application. Model ronda as two tables: `ronda_schedules` (one row per date) and `ronda_assignments` (one row per warga assigned to a schedule, carrying `checked_in_at`). Check-in is the public warga action, so it reuses the Phase 03 `App\Services\ResidentLookup` to resolve an active resident and then a new `App\Services\RondaCheckin` service that enforces the scheduling and once-only rules and returns a typed result. Admin scheduling lives under the authenticated `pengurus` dashboard and audits every mutation through `App\Support\Audit`. Public check-in is rate limited like the Phase 03 verification page. The list of "warga terjadwal yang belum check-in" (calon denda) is exposed as a query helper here but the denda transaction itself is deferred to Phase 06.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 02 (`Resident`, `Household`, `PhoneNumber`), and Phase 03 (`ResidentLookup`, `x-layouts.public`, `x-portal.phone-field`).

**Implementation status:** Complete. All task checkboxes below have been marked implemented. Final implementation includes atomic check-in updates, audit logging for check-in, active-resident server validation, eager loading for the assignment dropdown, WIB display labels, and a public `/jadwal-ronda` desktop table with mobile cards.

---

## Prerequisites

- Phases 01–03 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\Resident`, `App\Services\ResidentLookup`, `x-layouts.app`, `x-layouts.public`, and `x-portal.phone-field` all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `database/migrations/*_create_ronda_schedules_table.php`.
- Create: `database/migrations/*_create_ronda_assignments_table.php`.
- Create: `app/Models/RondaSchedule.php`.
- Create: `app/Models/RondaAssignment.php`.
- Create: `database/factories/RondaScheduleFactory.php`.
- Create: `database/factories/RondaAssignmentFactory.php`.
- Create: `app/Services/CheckinResult.php` (typed result object).
- Create: `app/Services/RondaCheckin.php` (check-in business rules).
- Modify: `routes/web.php` (public schedule + check-in routes, dashboard ronda routes).
- Create: `resources/views/livewire/dashboard/ronda/index.blade.php` (schedule list + create).
- Create: `resources/views/livewire/dashboard/ronda/show.blade.php` (assign warga, attendance view).
- Create: `resources/views/livewire/portal/ronda.blade.php` (public schedule view).
- Create: `resources/views/livewire/portal/checkin.blade.php` (warga check-in page).
- Modify: `resources/views/livewire/portal/home.blade.php` (enable Jadwal Ronda + Check-in entries).
- Modify: `resources/views/components/layouts/app.blade.php` (add Ronda nav link).
- Test: `tests/Feature/Ronda/RondaModelTest.php`.
- Test: `tests/Feature/Ronda/RondaCheckinServiceTest.php`.
- Test: `tests/Feature/Ronda/RondaScheduleManagementTest.php`.
- Test: `tests/Feature/Ronda/PublicCheckinTest.php`.

## Task 1: Create Ronda Schedule and Assignment Models

**Files:**

- Create: `database/migrations/*_create_ronda_schedules_table.php`
- Create: `database/migrations/*_create_ronda_assignments_table.php`
- Create: `app/Models/RondaSchedule.php`
- Create: `app/Models/RondaAssignment.php`
- Create: `database/factories/RondaScheduleFactory.php`
- Create: `database/factories/RondaAssignmentFactory.php`
- Test: `tests/Feature/Ronda/RondaModelTest.php`

- [x] **Step 1: Write failing model tests**

Create `tests/Feature/Ronda/RondaModelTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;

it('casts the schedule date and has many assignments', function () {
    $schedule = RondaSchedule::factory()->create(['date' => '2026-06-01']);
    $resident = Resident::factory()->for(Household::factory())->create();
    RondaAssignment::factory()->for($schedule)->for($resident)->create();

    expect($schedule->date->toDateString())->toBe('2026-06-01');
    expect($schedule->assignments)->toHaveCount(1);
});

it('marks an assignment present when checked in', function () {
    $assignment = RondaAssignment::factory()->create(['checked_in_at' => now()]);

    expect($assignment->hasCheckedIn())->toBeTrue();
});

it('reports an assignment without check-in as absent', function () {
    $assignment = RondaAssignment::factory()->create(['checked_in_at' => null]);

    expect($assignment->hasCheckedIn())->toBeFalse();
});

it('belongs to a resident and a schedule', function () {
    $assignment = RondaAssignment::factory()->create();

    expect($assignment->resident)->not->toBeNull();
    expect($assignment->rondaSchedule)->not->toBeNull();
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Ronda/RondaModelTest.php
```

Expected: FAIL because the ronda classes do not exist.

- [x] **Step 3: Create the schedules migration**

```bash
php artisan make:migration create_ronda_schedules_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('ronda_schedules', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        $table->text('notes')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('ronda_schedules');
}
```

- [x] **Step 4: Create the assignments migration**

```bash
php artisan make:migration create_ronda_assignments_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('ronda_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ronda_schedule_id')->constrained()->cascadeOnDelete();
        $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
        $table->timestamp('checked_in_at')->nullable();
        $table->timestamps();

        $table->unique(['ronda_schedule_id', 'resident_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('ronda_assignments');
}
```

- [x] **Step 5: Create the RondaSchedule model**

Create `app/Models/RondaSchedule.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RondaSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RondaAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [x] **Step 6: Create the RondaAssignment model**

Create `app/Models/RondaAssignment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondaAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ronda_schedule_id',
        'resident_id',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function rondaSchedule(): BelongsTo
    {
        return $this->belongsTo(RondaSchedule::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function hasCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }
}
```

- [x] **Step 7: Create factories**

Create `database/factories/RondaScheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\RondaSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaScheduleFactory extends Factory
{
    protected $model = RondaSchedule::class;

    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
```

Create `database/factories/RondaAssignmentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaAssignmentFactory extends Factory
{
    protected $model = RondaAssignment::class;

    public function definition(): array
    {
        return [
            'ronda_schedule_id' => RondaSchedule::factory(),
            'resident_id' => Resident::factory()->for(Household::factory()),
            'checked_in_at' => null,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['checked_in_at' => now()]);
    }
}
```

- [x] **Step 8: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Ronda/RondaModelTest.php
```

Expected: PASS.

- [x] **Step 9: Commit**

```bash
git add app/Models/RondaSchedule.php app/Models/RondaAssignment.php database/migrations database/factories/RondaScheduleFactory.php database/factories/RondaAssignmentFactory.php tests/Feature/Ronda/RondaModelTest.php
git commit -m "feat: add ronda schedule and assignment models"
```

## Task 2: Build the Ronda Check-in Service

**Files:**

- Create: `app/Services/CheckinResult.php`
- Create: `app/Services/RondaCheckin.php`
- Test: `tests/Feature/Ronda/RondaCheckinServiceTest.php`

- [x] **Step 1: Write failing service tests**

Create `tests/Feature/Ronda/RondaCheckinServiceTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Services\RondaCheckin;

beforeEach(function () {
    $this->service = app(RondaCheckin::class);
    $this->household = Household::factory()->create();
    $this->schedule = RondaSchedule::factory()->create(['date' => today()]);
});

it('checks in a scheduled active resident', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $result = $this->service->checkIn('0812-3456-7890', $this->schedule->date);

    expect($result->success())->toBeTrue();
    expect($result->assignment->fresh()->hasCheckedIn())->toBeTrue();
});

it('rejects an unregistered phone', function () {
    $result = $this->service->checkIn('0899-0000-0000', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('rejects a resident not scheduled that day', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->service->checkIn('81234567890', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP tidak terjadwal ronda hari ini.');
});

it('rejects a second check-in on the same date', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->checkedIn()->create();

    $result = $this->service->checkIn('81234567890', $this->schedule->date);

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Anda sudah check-in untuk tanggal ini.');
});

it('rejects when no schedule exists for the date', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->service->checkIn('81234567890', today()->addDay());

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Belum ada jadwal ronda untuk tanggal ini.');
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Ronda/RondaCheckinServiceTest.php
```

Expected: FAIL because the service classes do not exist.

- [x] **Step 3: Create the typed result object**

Create `app/Services/CheckinResult.php`:

```php
<?php

namespace App\Services;

use App\Models\RondaAssignment;

class CheckinResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?RondaAssignment $assignment = null,
        public readonly ?string $message = null,
    ) {}

    public static function done(RondaAssignment $assignment): self
    {
        return new self(ok: true, assignment: $assignment);
    }

    public static function fail(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    public function success(): bool
    {
        return $this->ok;
    }
}
```

- [x] **Step 4: Create the check-in service**

Create `app/Services/RondaCheckin.php`:

```php
<?php

namespace App\Services;

use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Support\Audit;
use Carbon\CarbonInterface;

class RondaCheckin
{
    public function __construct(
        protected ResidentLookup $lookup,
    ) {}

    public function checkIn(?string $rawPhone, CarbonInterface $date): CheckinResult
    {
        $lookup = $this->lookup->resolve($rawPhone);

        if (! $lookup->found()) {
            return CheckinResult::fail($lookup->message);
        }

        $schedule = RondaSchedule::query()
            ->whereDate('date', $date->toDateString())
            ->first();

        if (! $schedule) {
            return CheckinResult::fail('Belum ada jadwal ronda untuk tanggal ini.');
        }

        $assignment = RondaAssignment::query()
            ->where('ronda_schedule_id', $schedule->id)
            ->where('resident_id', $lookup->resident->id)
            ->first();

        if (! $assignment) {
            return CheckinResult::fail('Nomor HP tidak terjadwal ronda hari ini.');
        }

        if ($assignment->hasCheckedIn()) {
            return CheckinResult::fail('Anda sudah check-in untuk tanggal ini.');
        }

        $checkedInAt = now();

        $updated = RondaAssignment::query()
            ->whereKey($assignment->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => $checkedInAt]);

        if ($updated !== 1) {
            return CheckinResult::fail('Anda sudah check-in untuk tanggal ini.');
        }

        $assignment->refresh();

        Audit::record(auth()->user(), 'ronda.assignment.checked_in', 'ronda_assignment', $assignment->id, [
            'ronda_schedule_id' => $schedule->id,
            'resident_id' => $lookup->resident->id,
            'checked_in_at' => $checkedInAt->toIso8601String(),
        ]);

        return CheckinResult::done($assignment);
    }
}
```

- [x] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Ronda/RondaCheckinServiceTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add app/Services/CheckinResult.php app/Services/RondaCheckin.php tests/Feature/Ronda/RondaCheckinServiceTest.php
git commit -m "feat: add ronda check-in service"
```

## Task 3: Build the Ronda Schedule Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/ronda/index.blade.php`
- Create: `resources/views/livewire/dashboard/ronda/show.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Ronda/RondaScheduleManagementTest.php`

- [x] **Step 1: Write failing management tests**

Create `tests/Feature/Ronda/RondaScheduleManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaSchedule;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->household = Household::factory()->create();
});

it('blocks guests from ronda management', function () {
    $this->get('/dashboard/ronda')->assertRedirect('/login');
});

it('creates a schedule and writes an audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.index')
        ->set('date', today()->addDay()->toDateString())
        ->set('notes', 'Ronda malam')
        ->call('save')
        ->assertHasNoErrors();

    expect(RondaSchedule::query()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'ronda.schedule.created')->exists())->toBeTrue();
});

it('rejects a duplicate schedule date', function () {
    RondaSchedule::factory()->create(['date' => today()->addDay()]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.index')
        ->set('date', today()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors('date');
});

it('assigns an active resident to a schedule', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.ronda.show', ['schedule' => $schedule])
        ->set('residentId', $resident->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect($schedule->assignments()->where('resident_id', $resident->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'ronda.assignment.added')->exists())->toBeTrue();
});

it('does not assign the same resident twice', function () {
    $schedule = RondaSchedule::factory()->create();
    $resident = Resident::factory()->for($this->household)->create(['is_active' => true]);

    $this->actingAs($this->admin);

    $component = Volt::test('dashboard.ronda.show', ['schedule' => $schedule]);
    $component->set('residentId', $resident->id)->call('assign');
    $component->set('residentId', $resident->id)->call('assign');

    expect($schedule->assignments()->where('resident_id', $resident->id)->count())->toBe(1);
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Ronda/RondaScheduleManagementTest.php
```

Expected: FAIL because routes and views are missing.

- [x] **Step 3: Add dashboard routes**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/ronda', 'dashboard.ronda.index')->name('ronda.index');
Volt::route('/dashboard/ronda/{schedule}', 'dashboard.ronda.show')->name('ronda.show');
```

- [x] **Step 4: Create the schedule list page**

Create `resources/views/livewire/dashboard/ronda/index.blade.php`:

```blade
<?php

use App\Models\RondaSchedule;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed};

state(['date' => '', 'notes' => '']);

rules([
    'date' => ['required', 'date'],
    'notes' => ['nullable', 'string', 'max:500'],
]);

$schedules = computed(fn () => RondaSchedule::query()
    ->withCount(['assignments', 'assignments as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at')])
    ->orderByDesc('date')
    ->get());

$save = function () {
    $data = $this->validate();
    $data['date'] = \Illuminate\Support\Carbon::parse($data['date'])->toDateString();

    if (RondaSchedule::query()->whereDate('date', $data['date'])->exists()) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'date' => 'Jadwal ronda untuk tanggal ini sudah ada.',
        ]);
    }

    $data['created_by'] = auth()->id();

    $schedule = RondaSchedule::create($data);
    Audit::record(auth()->user(), 'ronda.schedule.created', 'ronda_schedule', $schedule->id, ['date' => $schedule->date->toDateString()]);

    $this->reset('date', 'notes');
};

?>

<x-layouts.app title="Jadwal Ronda">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Jadwal Ronda</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Tanggal</label>
                <input wire:model="date" type="date" class="mt-1 w-full rounded-lg border-slate-300">
                @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Catatan</label>
                <input wire:model="notes" type="text" class="mt-1 w-full rounded-lg border-slate-300">
            </div>
            <div class="flex items-end">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Tambah Jadwal</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2">Petugas</th>
                        <th class="px-4 py-2">Hadir</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->schedules as $schedule)
                        <tr>
                            <td class="px-4 py-2">{{ $schedule->date->format('d M Y') }}</td>
                            <td class="px-4 py-2">{{ $schedule->assignments_count }}</td>
                            <td class="px-4 py-2">{{ $schedule->checked_in_count }} / {{ $schedule->assignments_count }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('ronda.show', $schedule) }}" class="text-emerald-700 hover:underline">Kelola</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 5: Create the schedule detail page**

Create `resources/views/livewire/dashboard/ronda/show.blade.php`:

```blade
<?php

use App\Models\Resident;
use App\Models\RondaSchedule;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount};

state(['schedule' => null, 'residentId' => null]);

mount(function (RondaSchedule $schedule) {
    $this->schedule = $schedule;
});

$assignments = computed(fn () => $this->schedule->assignments()->with('resident.household')->get());

$availableResidents = computed(fn () => Resident::query()
    ->with('household')
    ->where('is_active', true)
    ->whereNotIn('id', $this->schedule->assignments()->pluck('resident_id'))
    ->orderBy('name')
    ->get());

$assign = function () {
    $this->validate([
        'residentId' => [
            'required',
            Rule::exists(Resident::class, 'id')->where('is_active', true),
        ],
    ]);

    $assignment = $this->schedule->assignments()->firstOrCreate(['resident_id' => $this->residentId]);

    if ($assignment->wasRecentlyCreated) {
        Audit::record(auth()->user(), 'ronda.assignment.added', 'ronda_schedule', $this->schedule->id, ['resident_id' => $this->residentId]);
    }

    $this->reset('residentId');
};

$remove = function (int $assignmentId) {
    $assignment = $this->schedule->assignments()->findOrFail($assignmentId);
    $residentId = $assignment->resident_id;
    $assignment->delete();

    Audit::record(auth()->user(), 'ronda.assignment.removed', 'ronda_schedule', $this->schedule->id, ['resident_id' => $residentId]);
};

?>

<x-layouts.app title="Kelola Jadwal Ronda">
    <div class="space-y-6">
        <div>
            <a href="{{ route('ronda.index') }}" class="text-sm text-emerald-700 hover:underline">&larr; Jadwal Ronda</a>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">Ronda {{ $schedule->date->format('d M Y') }}</h1>
            @if ($schedule->notes)
                <p class="text-slate-600">{{ $schedule->notes }}</p>
            @endif
        </div>

        <form wire:submit="assign" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700">Tambah Petugas</label>
                <select wire:model="residentId" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih warga aktif</option>
                    @foreach ($this->availableResidents as $resident)
                        <option value="{{ $resident->id }}">{{ $resident->name }} ({{ $resident->household?->house_number }})</option>
                    @endforeach
                </select>
                @error('residentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Tambah</button>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Rumah</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->assignments as $assignment)
                        <tr>
                            <td class="px-4 py-2">{{ $assignment->resident?->name }}</td>
                            <td class="px-4 py-2">{{ $assignment->resident?->household?->house_number }}</td>
                            <td class="px-4 py-2">
                                @if ($assignment->hasCheckedIn())
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Hadir {{ $assignment->checked_in_at->format('H:i') }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Belum check-in</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="remove({{ $assignment->id }})" class="text-slate-600 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [x] **Step 6: Add the Ronda nav link**

In `resources/views/components/layouts/app.blade.php`, add to the nav:

```blade
<a href="{{ route('ronda.index') }}" class="text-slate-600 hover:text-slate-900">Ronda</a>
```

- [x] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Ronda/RondaScheduleManagementTest.php
```

Expected: PASS.

- [x] **Step 8: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/ronda resources/views/components/layouts/app.blade.php tests/Feature/Ronda/RondaScheduleManagementTest.php
git commit -m "feat: add ronda schedule management dashboard"
```

## Task 4: Build the Public Schedule View and Check-in Page

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/portal/ronda.blade.php`
- Create: `resources/views/livewire/portal/checkin.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Ronda/PublicCheckinTest.php`

- [x] **Step 1: Write failing public check-in tests**

Create `tests/Feature/Ronda/PublicCheckinTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-checkin:127.0.0.1');
    $this->household = Household::factory()->create();
    $this->schedule = RondaSchedule::factory()->create(['date' => today()]);
});

it('shows the public ronda schedule without login', function () {
    $this->get('/jadwal-ronda')
        ->assertOk()
        ->assertSee('Jadwal Ronda');
});

it('checks in a scheduled resident from the portal', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'name' => 'Andi',
        'phone' => '81234567890',
        'is_active' => true,
    ]);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    Volt::test('portal.checkin')
        ->set('phone', '0812-3456-7890')
        ->call('submit')
        ->assertSee('Check-in berhasil');

    expect(RondaAssignment::query()->whereNotNull('checked_in_at')->count())->toBe(1);
});

it('rejects a resident not scheduled today', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.checkin')
        ->set('phone', '81234567890')
        ->call('submit')
        ->assertSee('tidak terjadwal');
});

it('rate limits repeated check-in attempts', function () {
    $component = Volt::test('portal.checkin');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->call('submit');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Ronda/PublicCheckinTest.php
```

Expected: FAIL because public routes and views are missing.

- [x] **Step 3: Add public routes**

In `routes/web.php`, with the other public portal routes (outside the auth group):

```php
Volt::route('/jadwal-ronda', 'portal.ronda')->name('portal.ronda');
Volt::route('/checkin-ronda', 'portal.checkin')->name('portal.checkin');
```

- [x] **Step 4: Create the public schedule view**

Create `resources/views/livewire/portal/ronda.blade.php`:

```blade
<?php

use App\Models\RondaSchedule;
use function Livewire\Volt\{computed};

$schedules = computed(fn () => RondaSchedule::query()
    ->with('assignments.resident')
    ->whereDate('date', '>=', today())
    ->orderBy('date')
    ->take(14)
    ->get());

?>

<x-layouts.public title="Jadwal Ronda">
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-slate-900">Jadwal Ronda</h1>

        @forelse ($this->schedules as $schedule)
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="font-medium text-emerald-700">{{ $schedule->date->translatedFormat('l, d M Y') }}</p>
                @if ($schedule->notes)
                    <p class="text-sm text-slate-500">{{ $schedule->notes }}</p>
                @endif
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($schedule->assignments as $assignment)
                        <li class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">
                            {{ $assignment->resident?->name }}
                            @if ($assignment->checked_in_at) <span class="text-emerald-600">&check;</span> @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-slate-500">Belum ada jadwal ronda.</p>
        @endforelse

        <div class="text-center">
            <a href="{{ route('portal.checkin') }}" class="text-sm text-emerald-700 hover:underline">Check-in ronda hari ini</a>
        </div>
    </div>
</x-layouts.public>
```

- [x] **Step 5: Create the public check-in page**

Create `resources/views/livewire/portal/checkin.blade.php`:

```blade
<?php

use App\Services\RondaCheckin;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules};

state(['phone' => '', 'done' => false, 'feedback' => null]);

rules(['phone' => ['required', 'string', 'max:30']]);

$submit = function (RondaCheckin $checkin) {
    $this->validate();

    $key = 'portal-checkin:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->done = false;
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $checkin->checkIn($this->phone, today());

    if ($result->success()) {
        $this->done = true;
        $this->feedback = null;
    } else {
        $this->done = false;
        $this->feedback = $result->message;
    }
};

?>

<x-layouts.public title="Check-in Ronda">
    <div class="space-y-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Check-in Ronda</h1>
            <p class="mt-1 text-sm text-slate-600">
                Masukkan nomor HP Anda untuk mencatat kehadiran ronda hari ini ({{ today()->translatedFormat('d M Y') }}).
            </p>

            <form wire:submit="submit" class="mt-4 space-y-4">
                <x-portal.phone-field model="phone" />
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
                    Check-in Hadir
                </button>
            </form>
        </div>

        @if ($done)
            <div class="rounded-2xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-200">
                <p class="text-lg font-semibold text-emerald-800">Check-in berhasil</p>
                <p class="mt-1 text-sm text-emerald-700">Kehadiran ronda Anda sudah tercatat. Terima kasih.</p>
            </div>
        @elseif ($feedback)
            <div class="rounded-2xl bg-amber-50 p-5 text-center ring-1 ring-amber-200">
                <p class="text-sm text-amber-700">{{ $feedback }}</p>
            </div>
        @endif

        <div class="text-center">
            <a href="{{ route('portal.ronda') }}" class="text-sm text-emerald-700 hover:underline">Lihat jadwal ronda</a>
        </div>
    </div>
</x-layouts.public>
```

- [x] **Step 6: Enable the portal home entries**

In `resources/views/livewire/portal/home.blade.php`, update the `Jadwal Ronda` entry to `'route' => 'portal.ronda', 'ready' => true`, and add a Check-in entry:

```php
['label' => 'Check-in Ronda', 'route' => 'portal.checkin', 'desc' => 'Catat kehadiran ronda Anda hari ini.', 'ready' => true],
```

- [x] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Ronda/PublicCheckinTest.php
```

Expected: PASS.

- [x] **Step 8: Commit**

```bash
git add routes/web.php resources/views/livewire/portal tests/Feature/Ronda/PublicCheckinTest.php
git commit -m "feat: add public ronda schedule and check-in pages"
```

## Final Verification

- [x] Run all checks:

```bash
ddev exec php artisan test
```

Expected: PASS. Last verified after Sprint 2 review fixes: 62 tests, 127 assertions.

- [x] Manual smoke test:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

- Login as pengurus, open `Ronda`, create a schedule for today, and assign a seeded active resident.
- Open `/jadwal-ronda` (no login) and confirm the schedule and assigned warga appear as a desktop table and mobile cards.
- Open `/checkin-ronda`, enter the assigned resident's phone, and confirm "Check-in berhasil".
- Re-submit the same phone and confirm the "sudah check-in" message.
- Enter an active but unassigned resident's phone and confirm the "tidak terjadwal" message.
- Back in the dashboard schedule detail, confirm the resident now shows "Hadir" with a time.
