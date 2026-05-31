# Phase 06 Denda Ronda Rp5.000, Rekap Kas, dan Koreksi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the kas loop. Build the calon-denda review list from ronda assignments that were scheduled but never checked in, let Admin/Bendahara set a Rp5.000 denda after review, provide the kas rekap (harian, mingguan, bulanan, rumah belum bayar, warga belum check-in), and implement non-destructive koreksi/pembatalan of transactions with a recorded reason. No transaction is ever hard-deleted.

**Architecture:** Continue the single Laravel 12 application. Reuse the Phase 05 `cash_transactions` table and `TransactionType` enum (`iuran_harian`, `denda`, `koreksi`) — no schema changes are needed except adding a nullable self-reference so a `koreksi`/cancellation can point at the transaction it reverses, plus a `cancelled_at` marker. Three new services keep controllers thin: `App\Services\DendaService` (review-gated Rp5.000 denda creation, idempotent per assignment), `App\Services\KasReport` (period sums + unpaid/uncheckedin queries), and `App\Services\TransactionCorrection` (mark a transaction cancelled and write an offsetting `koreksi` row with reason). All actions are pengurus-only and audited through `App\Support\Audit`. The denda follows the spec rule that pengurus review the candidate list before a denda is set, so denda is never automatic.

**Tech Stack:** Laravel 12, PHP 8.3, MySQL 8, Livewire 3, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 04 (`RondaSchedule`, `RondaAssignment`), and Phase 05 (`CashTransaction`, `TransactionType`).

---

## Prerequisites

- Phases 01–05 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\RondaSchedule`, `App\Models\RondaAssignment`, `App\Models\CashTransaction`, and `App\Enums\TransactionType` all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `database/migrations/*_add_correction_columns_to_cash_transactions_table.php`.
- Modify: `app/Models/CashTransaction.php` (add `reverses`/`corrections` relations, `isCancelled`, scopes).
- Create: `app/Services/DendaService.php`.
- Create: `app/Services/KasReport.php`.
- Create: `app/Services/TransactionCorrection.php`.
- Modify: `routes/web.php` (dashboard denda, rekap, and transaksi routes).
- Create: `resources/views/livewire/dashboard/denda/index.blade.php` (calon denda review + set denda).
- Create: `resources/views/livewire/dashboard/kas/index.blade.php` (rekap kas).
- Create: `resources/views/livewire/dashboard/kas/transactions.blade.php` (transaction list + koreksi/batal).
- Modify: `resources/views/components/layouts/app.blade.php` (add Denda + Kas nav links).
- Modify: `resources/views/livewire/dashboard/index.blade.php` (add today's kas summary cards).
- Test: `tests/Feature/Kas/DendaServiceTest.php`.
- Test: `tests/Feature/Kas/KasReportTest.php`.
- Test: `tests/Feature/Kas/TransactionCorrectionTest.php`.
- Test: `tests/Feature/Kas/DendaManagementTest.php`.
- Test: `tests/Feature/Kas/KasRekapPageTest.php`.

## Task 1: Extend Cash Transactions for Corrections

**Files:**

- Create: `database/migrations/*_add_correction_columns_to_cash_transactions_table.php`
- Modify: `app/Models/CashTransaction.php`
- Test: `tests/Feature/Kas/TransactionCorrectionModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Kas/TransactionCorrectionModelTest.php`:

```php
<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;

it('links a correction to the transaction it reverses', function () {
    $original = CashTransaction::factory()->create(['type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);
    $koreksi = CashTransaction::factory()->create([
        'type' => TransactionType::KOREKSI,
        'amount' => -500,
        'reverses_id' => $original->id,
        'reason' => 'Salah input',
    ]);

    expect($koreksi->reverses->is($original))->toBeTrue();
    expect($original->corrections)->toHaveCount(1);
});

it('marks a transaction cancelled', function () {
    $tx = CashTransaction::factory()->create(['cancelled_at' => now()]);

    expect($tx->isCancelled())->toBeTrue();
});

it('excludes cancelled rows from the active scope', function () {
    CashTransaction::factory()->create(['cancelled_at' => null]);
    CashTransaction::factory()->create(['cancelled_at' => now()]);

    expect(CashTransaction::query()->active()->count())->toBe(1);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/TransactionCorrectionModelTest.php
```

Expected: FAIL because the columns, relations, and scopes do not exist.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_correction_columns_to_cash_transactions_table --table=cash_transactions
```

Migration body:

```php
public function up(): void
{
    Schema::table('cash_transactions', function (Blueprint $table) {
        $table->foreignId('reverses_id')->nullable()->after('ronda_scan_session_id')
            ->constrained('cash_transactions')->nullOnDelete();
        $table->timestamp('cancelled_at')->nullable()->after('status');
        $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
            ->constrained('users')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('cash_transactions', function (Blueprint $table) {
        $table->dropConstrainedForeignId('reverses_id');
        $table->dropConstrainedForeignId('cancelled_by');
        $table->dropColumn('cancelled_at');
    });
}
```

- [ ] **Step 4: Update the model**

In `app/Models/CashTransaction.php`, add `reverses_id`, `cancelled_at`, and `cancelled_by` to `$fillable`, cast `cancelled_at` to `datetime`, and add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function reverses(): BelongsTo
{
    return $this->belongsTo(CashTransaction::class, 'reverses_id');
}

public function corrections(): HasMany
{
    return $this->hasMany(CashTransaction::class, 'reverses_id');
}

public function isCancelled(): bool
{
    return $this->cancelled_at !== null;
}

public function scopeActive(Builder $query): Builder
{
    return $query->whereNull('cancelled_at');
}

public function scopeDenda(Builder $query): Builder
{
    return $query->where('type', TransactionType::DENDA->value);
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Kas/TransactionCorrectionModelTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/CashTransaction.php database/migrations tests/Feature/Kas/TransactionCorrectionModelTest.php
git commit -m "feat: extend cash transactions for corrections"
```

## Task 2: Build the Denda Service

**Files:**

- Create: `app/Services/DendaService.php`
- Test: `tests/Feature/Kas/DendaServiceTest.php`

- [ ] **Step 1: Write failing service tests**

Create `tests/Feature/Kas/DendaServiceTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Services\DendaService;

beforeEach(function () {
    $this->service = app(DendaService::class);
    $this->schedule = RondaSchedule::factory()->create(['date' => today()->subDay()]);
});

it('lists scheduled residents who did not check in for a date', function () {
    $present = Resident::factory()->for(Household::factory())->create();
    $absent = Resident::factory()->for(Household::factory())->create();

    RondaAssignment::factory()->for($this->schedule)->for($present)->checkedIn()->create();
    RondaAssignment::factory()->for($this->schedule)->for($absent)->create();

    $candidates = $this->service->candidates($this->schedule->date);

    expect($candidates)->toHaveCount(1);
    expect($candidates->first()->resident_id)->toBe($absent->id);
});

it('records a 5000 denda for an absent assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $tx = $this->service->fine($assignment);

    expect($tx->amount)->toBe(5000);
    expect($tx->type->value)->toBe('denda');
    expect($tx->resident_id)->toBe($resident->id);
    expect($tx->date->toDateString())->toBe($this->schedule->date->toDateString());
});

it('is idempotent and does not double-fine the same assignment', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->service->fine($assignment);
    $this->service->fine($assignment);

    expect(\App\Models\CashTransaction::query()->denda()->count())->toBe(1);
});

it('refuses to fine an assignment that has checked in', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->checkedIn()->create();

    expect(fn () => $this->service->fine($assignment))
        ->toThrow(\InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/DendaServiceTest.php
```

Expected: FAIL because the service does not exist.

- [ ] **Step 3: Create the service**

Create `app/Services/DendaService.php`:

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\RondaAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DendaService
{
    public const AMOUNT = 5000;

    public function candidates(CarbonInterface $date): Collection
    {
        return RondaAssignment::query()
            ->with('resident.household')
            ->whereNull('checked_in_at')
            ->whereHas('rondaSchedule', fn ($q) => $q->whereDate('date', $date->toDateString()))
            ->get();
    }

    public function fine(RondaAssignment $assignment, ?User $actor = null): CashTransaction
    {
        if ($assignment->hasCheckedIn()) {
            throw new \InvalidArgumentException('Tidak bisa mendenda warga yang sudah check-in.');
        }

        $assignment->loadMissing('resident', 'rondaSchedule');
        $date = $assignment->rondaSchedule->date->toDateString();

        return CashTransaction::query()->firstOrCreate(
            [
                'type' => TransactionType::DENDA->value,
                'resident_id' => $assignment->resident_id,
                'date' => $date,
            ],
            [
                'household_id' => $assignment->resident?->household_id,
                'amount' => self::AMOUNT,
                'status' => 'lunas',
                'source' => 'denda_review',
                'recorded_by' => $actor?->id,
            ],
        );
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/Kas/DendaServiceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DendaService.php tests/Feature/Kas/DendaServiceTest.php
git commit -m "feat: add denda service with review candidates"
```

## Task 3: Build the Kas Report and Correction Services

**Files:**

- Create: `app/Services/KasReport.php`
- Create: `app/Services/TransactionCorrection.php`
- Test: `tests/Feature/Kas/KasReportTest.php`
- Test: `tests/Feature/Kas/TransactionCorrectionTest.php`

- [ ] **Step 1: Write failing kas report tests**

Create `tests/Feature/Kas/KasReportTest.php`:

```php
<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Services\KasReport;

beforeEach(function () {
    $this->report = new KasReport();
});

it('sums active transactions for a single day', function () {
    CashTransaction::factory()->count(3)->create(['date' => today(), 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);
    CashTransaction::factory()->create(['date' => today(), 'type' => TransactionType::DENDA, 'amount' => 5000]);

    $summary = $this->report->daily(today());

    expect($summary['iuran'])->toBe(1500);
    expect($summary['denda'])->toBe(5000);
    expect($summary['total'])->toBe(6500);
});

it('ignores cancelled transactions in totals', function () {
    CashTransaction::factory()->create(['date' => today(), 'amount' => 500]);
    CashTransaction::factory()->create(['date' => today(), 'amount' => 500, 'cancelled_at' => now()]);

    expect($this->report->daily(today())['total'])->toBe(500);
});

it('sums a date range for monthly totals', function () {
    CashTransaction::factory()->create(['date' => today()->startOfMonth(), 'amount' => 500]);
    CashTransaction::factory()->create(['date' => today()->endOfMonth(), 'amount' => 500]);

    $total = $this->report->rangeTotal(today()->copy()->startOfMonth(), today()->copy()->endOfMonth());

    expect($total)->toBe(1000);
});

it('lists households that have not paid iuran on a date', function () {
    $paid = Household::factory()->create(['is_active' => true]);
    $unpaid = Household::factory()->create(['is_active' => true]);
    CashTransaction::factory()->for($paid)->create(['date' => today(), 'type' => TransactionType::IURAN_HARIAN, 'amount' => 500]);

    $unpaidList = $this->report->unpaidHouseholds(today());

    expect($unpaidList->pluck('id'))->toContain($unpaid->id);
    expect($unpaidList->pluck('id'))->not->toContain($paid->id);
});
```

- [ ] **Step 2: Write failing correction tests**

Create `tests/Feature/Kas/TransactionCorrectionTest.php`:

```php
<?php

use App\Models\CashTransaction;
use App\Models\User;
use App\Services\TransactionCorrection;

beforeEach(function () {
    $this->service = app(TransactionCorrection::class);
    $this->actor = User::factory()->create();
});

it('cancels a transaction and writes an offsetting koreksi row', function () {
    $original = CashTransaction::factory()->create(['amount' => 500]);

    $koreksi = $this->service->cancel($original, 'Salah scan rumah', $this->actor);

    expect($original->fresh()->isCancelled())->toBeTrue();
    expect($original->fresh()->cancelled_by)->toBe($this->actor->id);
    expect($koreksi->type->value)->toBe('koreksi');
    expect($koreksi->amount)->toBe(-500);
    expect($koreksi->reverses_id)->toBe($original->id);
    expect($koreksi->reason)->toBe('Salah scan rumah');
});

it('does not cancel an already cancelled transaction', function () {
    $original = CashTransaction::factory()->create(['amount' => 500, 'cancelled_at' => now()]);

    expect(fn () => $this->service->cancel($original, 'dup', $this->actor))
        ->toThrow(\InvalidArgumentException::class);
});

it('requires a reason', function () {
    $original = CashTransaction::factory()->create(['amount' => 500]);

    expect(fn () => $this->service->cancel($original, '   ', $this->actor))
        ->toThrow(\InvalidArgumentException::class);
});
```

- [ ] **Step 3: Run failing tests**

```bash
php artisan test tests/Feature/Kas/KasReportTest.php tests/Feature/Kas/TransactionCorrectionTest.php
```

Expected: FAIL because the services do not exist.

- [ ] **Step 4: Create the kas report service**

Create `app/Services/KasReport.php`:

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class KasReport
{
    public function daily(CarbonInterface $date): array
    {
        $rows = CashTransaction::query()
            ->active()
            ->whereDate('date', $date->toDateString())
            ->get();

        $iuran = (int) $rows->where('type', TransactionType::IURAN_HARIAN)->sum('amount');
        $denda = (int) $rows->where('type', TransactionType::DENDA)->sum('amount');
        $koreksi = (int) $rows->where('type', TransactionType::KOREKSI)->sum('amount');

        return [
            'iuran' => $iuran,
            'denda' => $denda,
            'koreksi' => $koreksi,
            'total' => (int) $rows->sum('amount'),
        ];
    }

    public function rangeTotal(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) CashTransaction::query()
            ->active()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    public function unpaidHouseholds(CarbonInterface $date): Collection
    {
        return Household::query()
            ->where('is_active', true)
            ->whereDoesntHave('cashTransactions', function ($query) use ($date) {
                $query->whereNull('cancelled_at')
                    ->where('type', TransactionType::IURAN_HARIAN->value)
                    ->whereDate('date', $date->toDateString());
            })
            ->orderBy('house_number')
            ->get();
    }
}
```

- [ ] **Step 5: Add the household relation used by the report**

In `app/Models/Household.php`, add:

```php
public function cashTransactions(): HasMany
{
    return $this->hasMany(CashTransaction::class);
}
```

(import `Illuminate\Database\Eloquent\Relations\HasMany` if not already present).

- [ ] **Step 6: Create the correction service**

Create `app/Services/TransactionCorrection.php`:

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionCorrection
{
    public function cancel(CashTransaction $transaction, string $reason, User $actor): CashTransaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Alasan koreksi wajib diisi.');
        }

        if ($transaction->isCancelled()) {
            throw new \InvalidArgumentException('Transaksi ini sudah dibatalkan.');
        }

        return DB::transaction(function () use ($transaction, $reason, $actor) {
            $transaction->update([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            return CashTransaction::create([
                'date' => $transaction->date->toDateString(),
                'household_id' => $transaction->household_id,
                'resident_id' => $transaction->resident_id,
                'ronda_scan_session_id' => $transaction->ronda_scan_session_id,
                'reverses_id' => $transaction->id,
                'type' => TransactionType::KOREKSI,
                'amount' => -1 * $transaction->amount,
                'status' => 'koreksi',
                'source' => 'koreksi',
                'recorded_by' => $actor->id,
                'reason' => $reason,
            ]);
        });
    }
}
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Kas/KasReportTest.php tests/Feature/Kas/TransactionCorrectionTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/KasReport.php app/Services/TransactionCorrection.php app/Models/Household.php tests/Feature/Kas/KasReportTest.php tests/Feature/Kas/TransactionCorrectionTest.php
git commit -m "feat: add kas report and transaction correction services"
```

## Task 4: Build the Denda Review Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/denda/index.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Kas/DendaManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Kas/DendaManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->schedule = RondaSchedule::factory()->create(['date' => today()->subDay()]);
});

it('blocks guests from denda review', function () {
    $this->get('/dashboard/denda')->assertRedirect('/login');
});

it('shows absent residents as denda candidates', function () {
    $resident = Resident::factory()->for(Household::factory())->create(['name' => 'Joko']);
    RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->actingAs($this->admin)
        ->get('/dashboard/denda?date='.$this->schedule->date->toDateString())
        ->assertOk()
        ->assertSee('Joko');
});

it('sets a 5000 denda after review and audits it', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $assignment = RondaAssignment::factory()->for($this->schedule)->for($resident)->create();

    $this->actingAs($this->admin);

    Volt::test('dashboard.denda.index', ['date' => $this->schedule->date->toDateString()])
        ->call('fine', $assignment->id)
        ->assertHasNoErrors();

    expect(CashTransaction::query()->denda()->where('amount', 5000)->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kas.denda.created')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/DendaManagementTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/denda', 'dashboard.denda.index')->name('denda.index');
```

- [ ] **Step 4: Create the denda review page**

Create `resources/views/livewire/dashboard/denda/index.blade.php`:

```blade
<?php

use App\Models\RondaAssignment;
use App\Services\DendaService;
use App\Support\Audit;
use function Livewire\Volt\{state, computed, mount};

state(['date' => null]);

mount(function () {
    $this->date = request()->query('date', today()->subDay()->toDateString());
});

$candidates = computed(fn () => app(DendaService::class)->candidates(\Illuminate\Support\Carbon::parse($this->date)));

$fine = function (int $assignmentId) {
    $assignment = RondaAssignment::with('resident', 'rondaSchedule')->findOrFail($assignmentId);
    $tx = app(DendaService::class)->fine($assignment, auth()->user());

    Audit::record(auth()->user(), 'kas.denda.created', 'cash_transaction', $tx->id, [
        'resident_id' => $assignment->resident_id,
        'date' => $this->date,
    ]);
};

?>

<x-layouts.app title="Review Denda Ronda">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Review Denda Ronda</h1>
        <p class="text-sm text-slate-600">Warga terjadwal yang belum check-in. Tinjau dulu sebelum menetapkan denda Rp5.000.</p>

        <div class="flex items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Tanggal</label>
                <input wire:model.live="date" type="date" class="mt-1 rounded-lg border-slate-300">
            </div>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Rumah</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->candidates as $assignment)
                        <tr>
                            <td class="px-4 py-2">{{ $assignment->resident?->name }}</td>
                            <td class="px-4 py-2">{{ $assignment->resident?->household?->house_number }}</td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="fine({{ $assignment->id }})"
                                        wire:confirm="Tetapkan denda Rp5.000 untuk warga ini?"
                                        class="rounded-lg bg-red-600 px-3 py-1 text-white hover:bg-red-700">
                                    Denda Rp5.000
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Tidak ada calon denda untuk tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Add the nav link**

In `resources/views/components/layouts/app.blade.php`, add to the nav:

```blade
<a href="{{ route('denda.index') }}" class="text-slate-600 hover:text-slate-900">Denda</a>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Kas/DendaManagementTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/denda resources/views/components/layouts/app.blade.php tests/Feature/Kas/DendaManagementTest.php
git commit -m "feat: add denda review dashboard"
```

## Task 5: Build the Kas Rekap and Transaction Correction Pages

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/kas/index.blade.php`
- Create: `resources/views/livewire/dashboard/kas/transactions.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `resources/views/livewire/dashboard/index.blade.php`
- Test: `tests/Feature/Kas/KasRekapPageTest.php`

- [ ] **Step 1: Write failing page tests**

Create `tests/Feature/Kas/KasRekapPageTest.php`:

```php
<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashTransaction;
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
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/KasRekapPageTest.php
```

Expected: FAIL because routes and views are missing.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/kas', 'dashboard.kas.index')->name('kas.index');
Volt::route('/dashboard/kas/transaksi', 'dashboard.kas.transactions')->name('kas.transactions');
```

- [ ] **Step 4: Create the rekap page**

Create `resources/views/livewire/dashboard/kas/index.blade.php`:

```blade
<?php

use App\Services\KasReport;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{state, computed, mount};

state(['date' => null]);

mount(function () {
    $this->date = request()->query('date', today()->toDateString());
});

$report = fn () => app(KasReport::class);
$ref = fn () => Carbon::parse($this->date);

$daily = computed(fn () => $this->report()->daily($this->ref()));
$weekly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfWeek(), $this->ref()->copy()->endOfWeek()));
$monthly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfMonth(), $this->ref()->copy()->endOfMonth()));
$unpaid = computed(fn () => $this->report()->unpaidHouseholds($this->ref()));

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<x-layouts.app title="Rekap Kas">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">Rekap Kas</h1>
            <a href="{{ route('kas.transactions') }}" class="text-sm text-emerald-700 hover:underline">Daftar Transaksi</a>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Tanggal Acuan</label>
            <input wire:model.live="date" type="date" class="mt-1 rounded-lg border-slate-300">
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Total Harian</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $this->rupiah($this->daily['total']) }}</p>
                <p class="mt-1 text-xs text-slate-400">Iuran {{ $this->rupiah($this->daily['iuran']) }} · Denda {{ $this->rupiah($this->daily['denda']) }} · Koreksi {{ $this->rupiah($this->daily['koreksi']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Total Mingguan</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $this->rupiah($this->weekly) }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Total Bulanan</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $this->rupiah($this->monthly) }}</p>
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-medium text-slate-900">Rumah Belum Bayar ({{ $this->ref()->format('d M Y') }})</h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @forelse ($this->unpaid as $household)
                    <li class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $household->house_number }} - {{ $household->head_name }}</li>
                @empty
                    <li class="text-sm text-slate-400">Semua rumah aktif sudah bayar.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Create the transactions page**

Create `resources/views/livewire/dashboard/kas/transactions.blade.php`:

```blade
<?php

use App\Models\CashTransaction;
use App\Services\TransactionCorrection;
use App\Support\Audit;
use function Livewire\Volt\{state, computed, rules};

state(['cancelId' => null, 'reason' => '']);

rules(['reason' => ['required', 'string', 'min:3', 'max:500']]);

$transactions = computed(fn () => CashTransaction::query()
    ->with(['household', 'resident'])
    ->latest()
    ->take(100)
    ->get());

$startCancel = function (int $id) {
    $this->cancelId = $id;
    $this->reset('reason');
};

$confirmCancel = function () {
    $this->validate();

    $tx = CashTransaction::findOrFail($this->cancelId);
    $koreksi = app(TransactionCorrection::class)->cancel($tx, $this->reason, auth()->user());

    Audit::record(auth()->user(), 'kas.transaction.cancelled', 'cash_transaction', $tx->id, [
        'koreksi_id' => $koreksi->id,
        'reason' => $this->reason,
    ]);

    $this->reset('cancelId', 'reason');
};

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<x-layouts.app title="Daftar Transaksi Kas">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">Daftar Transaksi Kas</h1>
            <a href="{{ route('kas.index') }}" class="text-sm text-emerald-700 hover:underline">Rekap Kas</a>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Rumah/Warga</th>
                        <th class="px-4 py-2 text-right">Nominal</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->transactions as $tx)
                        <tr class="{{ $tx->isCancelled() ? 'bg-slate-50 text-slate-400' : '' }}">
                            <td class="px-4 py-2">{{ $tx->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $tx->type->label() }}</td>
                            <td class="px-4 py-2">{{ $tx->household?->house_number ?? $tx->resident?->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->rupiah($tx->amount) }}</td>
                            <td class="px-4 py-2">
                                @if ($tx->isCancelled())
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">Dibatalkan</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{{ $tx->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if (! $tx->isCancelled() && $tx->type->value !== 'koreksi')
                                    <button wire:click="startCancel({{ $tx->id }})" class="text-red-600 hover:underline">Batalkan</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($cancelId)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-red-200">
                <h2 class="font-medium text-slate-900">Batalkan Transaksi #{{ $cancelId }}</h2>
                <p class="text-sm text-slate-600">Transaksi tidak dihapus. Sistem mencatat koreksi dengan alasan.</p>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-slate-700">Alasan</label>
                    <textarea wire:model="reason" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="mt-3 flex gap-2">
                    <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700">Konfirmasi Pembatalan</button>
                    <button wire:click="$set('cancelId', null)" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Add nav link and dashboard summary**

In `resources/views/components/layouts/app.blade.php`, add to the nav:

```blade
<a href="{{ route('kas.index') }}" class="text-slate-600 hover:text-slate-900">Kas</a>
```

In `resources/views/livewire/dashboard/index.blade.php`, add a today's kas total card using `KasReport`:

```php
use App\Services\KasReport;

$kasToday = computed(fn () => app(KasReport::class)->daily(today())['total']);
```

```blade
<div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <p class="text-sm text-slate-500">Kas Hari Ini</p>
    <p class="mt-1 text-3xl font-semibold text-emerald-700">Rp{{ number_format($this->kasToday, 0, ',', '.') }}</p>
</div>
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Kas/KasRekapPageTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard tests/Feature/Kas/KasRekapPageTest.php
git commit -m "feat: add kas rekap and transaction correction pages"
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

- Create a ronda schedule for yesterday with two assigned warga; check in one of them.
- Open `Denda`, set the date to yesterday, and confirm only the absent warga appears as a candidate.
- Click `Denda Rp5.000`, confirm the prompt, and verify the denda is recorded (and re-clicking does not double-fine).
- Open `Kas` and confirm daily/weekly/monthly totals and the "Rumah Belum Bayar" list.
- Open `Daftar Transaksi`, cancel an iuran with a reason, and confirm the row shows "Dibatalkan", a `koreksi` row offsets the amount, and the rekap total drops accordingly.
- Confirm `audit_logs` has `kas.denda.created` and `kas.transaction.cancelled` entries.
