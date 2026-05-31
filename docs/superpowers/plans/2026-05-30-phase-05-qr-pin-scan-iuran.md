# Phase 05 QR Rumah, Sesi PIN Harian, dan Scan Iuran Rp500 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the daily kas ronda collection flow. Admin/Bendahara open a scan session for a date that generates a 4-6 digit daily PIN active within a time window. Regu ronda (no login) open the public scan page, enter the active PIN, scan a rumah QR (the Phase 02 `qr_token`), and record a Rp500 daily iuran as lunas. A rumah can only be marked lunas once per date, scanning requires an active PIN, and every transaction stores the date, household, amount, session, and source.

**Architecture:** Continue the single Laravel 12 application. Introduce the `cash_transactions` table now, designed to carry all transaction types from the spec (`iuran_harian`, `denda`, `koreksi`) so Phase 06 (denda + rekap + koreksi) needs no schema churn; this phase only writes `iuran_harian`. Daily PINs live in `ronda_scan_sessions` (one per date, with a datetime window that may cross midnight). The scan flow is PIN-gated rather than login-gated because regu ronda are warga, not pengurus: a public `portal.scan` page composes two services — `App\Services\PinGate` (validate PIN + active window) and `App\Services\IuranScan` (resolve household by token, enforce once-per-date, record Rp500). PIN unlock is rate limited like other public endpoints. Session creation/regeneration lives under the authenticated `pengurus` dashboard and audits through `App\Support\Audit`.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 02 (`Household` + `qr_token`), and Phase 03 (`x-layouts.public`, rate-limit pattern).

---

## Prerequisites

- Phases 01–04 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\Household` (with `qr_token` + `is_active`), `x-layouts.app`, and `x-layouts.public` all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `app/Enums/TransactionType.php` (`iuran_harian`, `denda`, `koreksi`).
- Create: `database/migrations/*_create_cash_transactions_table.php`.
- Create: `app/Models/CashTransaction.php`.
- Create: `database/factories/CashTransactionFactory.php`.
- Create: `database/migrations/*_create_ronda_scan_sessions_table.php`.
- Create: `app/Models/RondaScanSession.php`.
- Create: `database/factories/RondaScanSessionFactory.php`.
- Create: `app/Services/PinGateResult.php` and `app/Services/PinGate.php`.
- Create: `app/Services/IuranResult.php` and `app/Services/IuranScan.php`.
- Modify: `routes/web.php` (dashboard session routes + public scan route).
- Create: `resources/views/livewire/dashboard/scan/index.blade.php` (session list + create/regenerate).
- Create: `resources/views/livewire/portal/scan.blade.php` (PIN unlock + scan iuran).
- Modify: `resources/views/livewire/portal/home.blade.php` (enable Scan Iuran entry).
- Modify: `resources/views/components/layouts/app.blade.php` (add Sesi Scan nav link).
- Test: `tests/Feature/Kas/CashTransactionModelTest.php`.
- Test: `tests/Feature/Kas/RondaScanSessionModelTest.php`.
- Test: `tests/Feature/Kas/PinGateTest.php`.
- Test: `tests/Feature/Kas/IuranScanServiceTest.php`.
- Test: `tests/Feature/Kas/ScanSessionManagementTest.php`.
- Test: `tests/Feature/Kas/PublicScanIuranTest.php`.

## Task 1: Create the Cash Transaction Model

**Files:**

- Create: `app/Enums/TransactionType.php`
- Create: `database/migrations/*_create_cash_transactions_table.php`
- Create: `app/Models/CashTransaction.php`
- Create: `database/factories/CashTransactionFactory.php`
- Test: `tests/Feature/Kas/CashTransactionModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Kas/CashTransactionModelTest.php`:

```php
<?php

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;

it('casts type to enum and date to a date', function () {
    $tx = CashTransaction::factory()->create([
        'type' => TransactionType::IURAN_HARIAN,
        'date' => '2026-06-01',
        'amount' => 500,
    ]);

    expect($tx->fresh()->type)->toBe(TransactionType::IURAN_HARIAN);
    expect($tx->date->toDateString())->toBe('2026-06-01');
    expect($tx->amount)->toBe(500);
});

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $tx = CashTransaction::factory()->for($household)->create();

    expect($tx->household->is($household))->toBeTrue();
});

it('scopes iuran harian transactions', function () {
    CashTransaction::factory()->create(['type' => TransactionType::IURAN_HARIAN]);
    CashTransaction::factory()->create(['type' => TransactionType::DENDA]);

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/CashTransactionModelTest.php
```

Expected: FAIL because the enum, migration, and model do not exist.

- [ ] **Step 3: Create the transaction type enum**

Create `app/Enums/TransactionType.php`:

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case IURAN_HARIAN = 'iuran_harian';
    case DENDA = 'denda';
    case KOREKSI = 'koreksi';

    public function label(): string
    {
        return match ($this) {
            self::IURAN_HARIAN => 'Iuran Harian',
            self::DENDA => 'Denda Ronda',
            self::KOREKSI => 'Koreksi',
        };
    }
}
```

- [ ] **Step 4: Create the migration**

```bash
php artisan make:migration create_cash_transactions_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('cash_transactions', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('ronda_scan_session_id')->nullable();
        $table->string('type');
        $table->integer('amount');
        $table->string('status')->default('lunas');
        $table->string('source')->default('scan');
        $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('reason')->nullable();
        $table->timestamps();

        $table->index(['date', 'type']);
        $table->index(['household_id', 'date', 'type']);
    });
}

public function down(): void
{
    Schema::dropIfExists('cash_transactions');
}
```

> Note: `ronda_scan_session_id` is left without a FK constraint here because the sessions table is created in Task 2. The model still exposes the relationship. Uniqueness of one `iuran_harian` per household per date is enforced in the application layer (Task 4) so it does not block multiple `koreksi` rows.

- [ ] **Step 5: Create the model**

Create `app/Models/CashTransaction.php`:

```php
<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'household_id',
        'resident_id',
        'ronda_scan_session_id',
        'type',
        'amount',
        'status',
        'source',
        'recorded_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'amount' => 'integer',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scanSession(): BelongsTo
    {
        return $this->belongsTo(RondaScanSession::class, 'ronda_scan_session_id');
    }

    public function scopeIuranHarian(Builder $query): Builder
    {
        return $query->where('type', TransactionType::IURAN_HARIAN->value);
    }
}
```

- [ ] **Step 6: Create the factory**

Create `database/factories/CashTransactionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashTransactionFactory extends Factory
{
    protected $model = CashTransaction::class;

    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'household_id' => Household::factory(),
            'type' => TransactionType::IURAN_HARIAN,
            'amount' => 500,
            'status' => 'lunas',
            'source' => 'scan',
        ];
    }
}
```

- [ ] **Step 7: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Kas/CashTransactionModelTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/TransactionType.php app/Models/CashTransaction.php database/migrations database/factories/CashTransactionFactory.php tests/Feature/Kas/CashTransactionModelTest.php
git commit -m "feat: add cash transaction model and transaction types"
```

## Task 2: Create the Ronda Scan Session Model with Daily PIN

**Files:**

- Create: `database/migrations/*_create_ronda_scan_sessions_table.php`
- Create: `app/Models/RondaScanSession.php`
- Create: `database/factories/RondaScanSessionFactory.php`
- Test: `tests/Feature/Kas/RondaScanSessionModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Kas/RondaScanSessionModelTest.php`:

```php
<?php

use App\Models\RondaScanSession;

it('generates a numeric pin on create', function () {
    $session = RondaScanSession::factory()->create(['pin' => null]);

    expect($session->pin)->toMatch('/^\d{4,6}$/');
});

it('does not overwrite an explicit pin', function () {
    $session = RondaScanSession::factory()->create(['pin' => '123456']);

    expect($session->fresh()->pin)->toBe('123456');
});

it('reports active when now is inside the window', function () {
    $session = RondaScanSession::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    expect($session->isActive())->toBeTrue();
});

it('reports expired when now is past the window', function () {
    $session = RondaScanSession::factory()->create([
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->subHour(),
    ]);

    expect($session->isActive())->toBeFalse();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/RondaScanSessionModelTest.php
```

Expected: FAIL because the migration and model do not exist.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_ronda_scan_sessions_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('ronda_scan_sessions', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        $table->string('pin');
        $table->dateTime('starts_at');
        $table->dateTime('ends_at');
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index('pin');
    });
}

public function down(): void
{
    Schema::dropIfExists('ronda_scan_sessions');
}
```

- [ ] **Step 4: Create the model**

Create `app/Models/RondaScanSession.php`:

```php
<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RondaScanSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'pin',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RondaScanSession $session) {
            if (blank($session->pin)) {
                $session->pin = self::generatePin();
            }
        });
    }

    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $now->betweenIncluded($this->starts_at, $this->ends_at);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'ronda_scan_session_id');
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/RondaScanSessionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\RondaScanSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class RondaScanSessionFactory extends Factory
{
    protected $model = RondaScanSession::class;

    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'pin' => '123456',
            'starts_at' => today()->setTime(18, 0),
            'ends_at' => today()->addDay()->setTime(6, 0),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Kas/RondaScanSessionModelTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/RondaScanSession.php database/migrations database/factories/RondaScanSessionFactory.php tests/Feature/Kas/RondaScanSessionModelTest.php
git commit -m "feat: add ronda scan session with daily pin"
```

## Task 3: Build the PIN Gate and Iuran Scan Services

**Files:**

- Create: `app/Services/PinGateResult.php`
- Create: `app/Services/PinGate.php`
- Create: `app/Services/IuranResult.php`
- Create: `app/Services/IuranScan.php`
- Test: `tests/Feature/Kas/PinGateTest.php`
- Test: `tests/Feature/Kas/IuranScanServiceTest.php`

- [ ] **Step 1: Write failing PIN gate tests**

Create `tests/Feature/Kas/PinGateTest.php`:

```php
<?php

use App\Models\RondaScanSession;
use App\Services\PinGate;

beforeEach(function () {
    $this->gate = new PinGate();
});

it('unlocks with a valid active pin', function () {
    $session = RondaScanSession::factory()->active()->create(['pin' => '654321']);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeTrue();
    expect($result->session->is($session))->toBeTrue();
});

it('rejects an unknown pin', function () {
    RondaScanSession::factory()->active()->create(['pin' => '654321']);

    $result = $this->gate->unlock('000000');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN tidak ditemukan.');
});

it('rejects an expired pin', function () {
    RondaScanSession::factory()->expired()->create(['pin' => '654321']);

    $result = $this->gate->unlock('654321');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN sudah kedaluwarsa.');
});

it('rejects a blank pin without querying', function () {
    $result = $this->gate->unlock('');

    expect($result->ok())->toBeFalse();
    expect($result->message)->toBe('PIN wajib diisi.');
});
```

- [ ] **Step 2: Write failing iuran scan tests**

Create `tests/Feature/Kas/IuranScanServiceTest.php`:

```php
<?php

use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaScanSession;
use App\Services\IuranScan;

beforeEach(function () {
    $this->service = new IuranScan();
    $this->session = RondaScanSession::factory()->active()->create(['date' => today()]);
    $this->household = Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-1', 'is_active' => true]);
});

it('records a 500 iuran for an unpaid household', function () {
    $result = $this->service->record($this->session, 'HOUSE-TOKEN-1');

    expect($result->paid())->toBeTrue();
    expect($result->transaction->amount)->toBe(500);
    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('reports already paid on a second scan for the same date', function () {
    $this->service->record($this->session, 'HOUSE-TOKEN-1');

    $result = $this->service->record($this->session, 'HOUSE-TOKEN-1');

    expect($result->paid())->toBeFalse();
    expect($result->status)->toBe('already_paid');
    expect($result->message)->toBe('Iuran rumah ini sudah tercatat hari ini.');
    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('rejects an unknown qr token', function () {
    $result = $this->service->record($this->session, 'NOPE');

    expect($result->paid())->toBeFalse();
    expect($result->message)->toBe('QR rumah tidak dikenali.');
});

it('rejects a token for an inactive household', function () {
    Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-2', 'is_active' => false]);

    $result = $this->service->record($this->session, 'HOUSE-TOKEN-2');

    expect($result->paid())->toBeFalse();
    expect($result->message)->toBe('QR rumah tidak dikenali.');
});
```

- [ ] **Step 3: Run failing tests**

```bash
php artisan test tests/Feature/Kas/PinGateTest.php tests/Feature/Kas/IuranScanServiceTest.php
```

Expected: FAIL because the service classes do not exist.

- [ ] **Step 4: Create the PIN gate result and service**

Create `app/Services/PinGateResult.php`:

```php
<?php

namespace App\Services;

use App\Models\RondaScanSession;

class PinGateResult
{
    public function __construct(
        public readonly ?RondaScanSession $session = null,
        public readonly ?string $message = null,
    ) {}

    public static function open(RondaScanSession $session): self
    {
        return new self(session: $session);
    }

    public static function deny(string $message): self
    {
        return new self(message: $message);
    }

    public function ok(): bool
    {
        return $this->session !== null;
    }
}
```

Create `app/Services/PinGate.php`:

```php
<?php

namespace App\Services;

use App\Models\RondaScanSession;

class PinGate
{
    public function unlock(?string $pin): PinGateResult
    {
        $pin = trim((string) $pin);

        if ($pin === '') {
            return PinGateResult::deny('PIN wajib diisi.');
        }

        $session = RondaScanSession::query()->where('pin', $pin)->first();

        if (! $session) {
            return PinGateResult::deny('PIN tidak ditemukan.');
        }

        if (! $session->isActive()) {
            return PinGateResult::deny('PIN sudah kedaluwarsa.');
        }

        return PinGateResult::open($session);
    }
}
```

- [ ] **Step 5: Create the iuran result and scan service**

Create `app/Services/IuranResult.php`:

```php
<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\Household;

class IuranResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?Household $household = null,
        public readonly ?CashTransaction $transaction = null,
        public readonly ?string $message = null,
    ) {}

    public static function recorded(Household $household, CashTransaction $transaction): self
    {
        return new self(status: 'paid', household: $household, transaction: $transaction);
    }

    public static function alreadyPaid(Household $household, CashTransaction $transaction): self
    {
        return new self(
            status: 'already_paid',
            household: $household,
            transaction: $transaction,
            message: 'Iuran rumah ini sudah tercatat hari ini.',
        );
    }

    public static function error(string $message): self
    {
        return new self(status: 'error', message: $message);
    }

    public function paid(): bool
    {
        return $this->status === 'paid';
    }
}
```

Create `app/Services/IuranScan.php`:

```php
<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaScanSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IuranScan
{
    public const AMOUNT = 500;

    public function record(RondaScanSession $session, ?string $token, ?User $actor = null): IuranResult
    {
        $token = trim((string) $token);

        if ($token === '') {
            return IuranResult::error('QR rumah tidak dikenali.');
        }

        $household = Household::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $household) {
            return IuranResult::error('QR rumah tidak dikenali.');
        }

        return DB::transaction(function () use ($session, $household, $actor) {
            $existing = CashTransaction::query()
                ->iuranHarian()
                ->where('household_id', $household->id)
                ->whereDate('date', $session->date->toDateString())
                ->where('status', 'lunas')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return IuranResult::alreadyPaid($household, $existing);
            }

            $transaction = CashTransaction::create([
                'date' => $session->date->toDateString(),
                'household_id' => $household->id,
                'ronda_scan_session_id' => $session->id,
                'type' => TransactionType::IURAN_HARIAN,
                'amount' => self::AMOUNT,
                'status' => 'lunas',
                'source' => 'scan',
                'recorded_by' => $actor?->id,
            ]);

            return IuranResult::recorded($household, $transaction);
        });
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Kas/PinGateTest.php tests/Feature/Kas/IuranScanServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/PinGateResult.php app/Services/PinGate.php app/Services/IuranResult.php app/Services/IuranScan.php tests/Feature/Kas/PinGateTest.php tests/Feature/Kas/IuranScanServiceTest.php
git commit -m "feat: add pin gate and iuran scan services"
```

## Task 4: Build the Scan Session Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/scan/index.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Kas/ScanSessionManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Kas/ScanSessionManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\RondaScanSession;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from scan session management', function () {
    $this->get('/dashboard/sesi-scan')->assertRedirect('/login');
});

it('creates a session with a generated pin and audit log', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->set('date', today()->toDateString())
        ->set('starts_at', today()->setTime(18, 0)->format('Y-m-d\TH:i'))
        ->set('ends_at', today()->addDay()->setTime(6, 0)->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $session = RondaScanSession::query()->first();
    expect($session)->not->toBeNull();
    expect($session->pin)->toMatch('/^\d{4,6}$/');
    expect(AuditLog::query()->where('action', 'ronda.scan_session.created')->exists())->toBeTrue();
});

it('rejects a duplicate session date', function () {
    RondaScanSession::factory()->create(['date' => today()]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->set('date', today()->toDateString())
        ->set('starts_at', today()->setTime(18, 0)->format('Y-m-d\TH:i'))
        ->set('ends_at', today()->addDay()->setTime(6, 0)->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('date');
});

it('regenerates a pin and audits the change', function () {
    $session = RondaScanSession::factory()->create(['pin' => '111111']);

    $this->actingAs($this->admin);

    Volt::test('dashboard.scan.index')
        ->call('regenerate', $session->id);

    expect($session->fresh()->pin)->not->toBe('111111');
    expect(AuditLog::query()->where('action', 'ronda.scan_session.pin_regenerated')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/ScanSessionManagementTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the dashboard route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/sesi-scan', 'dashboard.scan.index')->name('scan-sessions.index');
```

- [ ] **Step 4: Create the session management page**

Create `resources/views/livewire/dashboard/scan/index.blade.php`:

```blade
<?php

use App\Models\RondaScanSession;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed};

state([
    'date' => '',
    'starts_at' => '',
    'ends_at' => '',
]);

rules([
    'date' => ['required', 'date', 'unique:ronda_scan_sessions,date'],
    'starts_at' => ['required', 'date'],
    'ends_at' => ['required', 'date', 'after:starts_at'],
]);

$sessions = computed(fn () => RondaScanSession::query()
    ->withCount('transactions')
    ->withSum('transactions', 'amount')
    ->orderByDesc('date')
    ->get());

$save = function () {
    $data = $this->validate();
    $data['created_by'] = auth()->id();

    $session = RondaScanSession::create($data);
    Audit::record(auth()->user(), 'ronda.scan_session.created', 'ronda_scan_session', $session->id, ['date' => $session->date->toDateString()]);

    $this->reset('date', 'starts_at', 'ends_at');
};

$regenerate = function (int $id) {
    $session = RondaScanSession::findOrFail($id);
    $session->update(['pin' => RondaScanSession::generatePin()]);
    Audit::record(auth()->user(), 'ronda.scan_session.pin_regenerated', 'ronda_scan_session', $session->id, []);
};

?>

<x-layouts.app title="Sesi Scan Ronda">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Sesi Scan Ronda</h1>
        <p class="text-sm text-slate-600">Buat sesi harian untuk membuka mode scan iuran Rp500. Bagikan PIN ke regu ronda lewat WhatsApp.</p>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Tanggal</label>
                <input wire:model="date" type="date" class="mt-1 w-full rounded-lg border-slate-300">
                @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Mulai</label>
                <input wire:model="starts_at" type="datetime-local" class="mt-1 w-full rounded-lg border-slate-300">
                @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Selesai</label>
                <input wire:model="ends_at" type="datetime-local" class="mt-1 w-full rounded-lg border-slate-300">
                @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Buat Sesi</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2">PIN</th>
                        <th class="px-4 py-2">Jendela Waktu</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Terkumpul</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->sessions as $session)
                        <tr>
                            <td class="px-4 py-2">{{ $session->date->format('d M Y') }}</td>
                            <td class="px-4 py-2 font-mono text-base tracking-widest text-emerald-700">{{ $session->pin }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $session->starts_at->format('d/m H:i') }} - {{ $session->ends_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $session->isActive() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $session->isActive() ? 'Aktif' : 'Kedaluwarsa' }}
                                </span>
                            </td>
                            <td class="px-4 py-2">Rp{{ number_format((int) $session->transactions_sum_amount, 0, ',', '.') }} ({{ $session->transactions_count }})</td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="regenerate({{ $session->id }})" class="text-slate-600 hover:underline">PIN Baru</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Add the nav link**

In `resources/views/components/layouts/app.blade.php`, add to the nav:

```blade
<a href="{{ route('scan-sessions.index') }}" class="text-slate-600 hover:text-slate-900">Sesi Scan</a>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Kas/ScanSessionManagementTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/scan resources/views/components/layouts/app.blade.php tests/Feature/Kas/ScanSessionManagementTest.php
git commit -m "feat: add scan session management dashboard"
```

## Task 5: Build the Public Scan Iuran Page

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/portal/scan.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Kas/PublicScanIuranTest.php`

- [ ] **Step 1: Write failing public scan tests**

Create `tests/Feature/Kas/PublicScanIuranTest.php`:

```php
<?php

use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\RondaScanSession;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('scan-unlock:127.0.0.1');
    $this->session = RondaScanSession::factory()->active()->create(['date' => today(), 'pin' => '654321']);
    $this->household = Household::factory()->create(['qr_token' => 'HOUSE-TOKEN-1', 'head_name' => 'Budi', 'is_active' => true]);
});

it('serves the scan page without login', function () {
    $this->get('/scan-iuran')->assertOk()->assertSee('Scan Iuran');
});

it('unlocks the scan mode with a valid pin', function () {
    Volt::test('portal.scan')
        ->set('pin', '654321')
        ->call('unlock')
        ->assertSet('unlocked', true);
});

it('rejects an expired pin', function () {
    $this->session->update(['ends_at' => now()->subHour(), 'starts_at' => now()->subHours(3)]);

    Volt::test('portal.scan')
        ->set('pin', '654321')
        ->call('unlock')
        ->assertSet('unlocked', false)
        ->assertSee('kedaluwarsa');
});

it('records iuran when a valid token is scanned', function () {
    $component = Volt::test('portal.scan')->set('pin', '654321')->call('unlock');

    $component->set('token', 'HOUSE-TOKEN-1')->call('scan')
        ->assertSee('Budi')
        ->assertSee('Lunas');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('reports already paid on a duplicate scan', function () {
    $component = Volt::test('portal.scan')->set('pin', '654321')->call('unlock');
    $component->set('token', 'HOUSE-TOKEN-1')->call('scan');
    $component->set('token', 'HOUSE-TOKEN-1')->call('scan')
        ->assertSee('sudah tercatat');

    expect(CashTransaction::query()->iuranHarian()->count())->toBe(1);
});

it('rate limits repeated unlock attempts', function () {
    $component = Volt::test('portal.scan');

    foreach (range(1, 6) as $ignored) {
        $component->set('pin', '000000')->call('unlock');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Kas/PublicScanIuranTest.php
```

Expected: FAIL because the public route and view are missing.

- [ ] **Step 3: Add the public route**

In `routes/web.php`, with the other public portal routes (outside the auth group):

```php
Volt::route('/scan-iuran', 'portal.scan')->name('portal.scan');
```

- [ ] **Step 4: Create the scan page**

Create `resources/views/livewire/portal/scan.blade.php`:

```blade
<?php

use App\Services\IuranScan;
use App\Services\PinGate;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules};

state([
    'pin' => '',
    'unlocked' => false,
    'sessionId' => null,
    'unlockError' => null,
    'token' => '',
    'lastResult' => null,
]);

rules(['pin' => ['required', 'string', 'max:6']]);

$unlock = function (PinGate $gate) {
    $this->validate();

    $key = 'scan-unlock:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->unlocked = false;
        $this->unlockError = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $gate->unlock($this->pin);

    if ($result->ok()) {
        $this->unlocked = true;
        $this->sessionId = $result->session->id;
        $this->unlockError = null;
        RateLimiter::clear($key);
    } else {
        $this->unlocked = false;
        $this->unlockError = $result->message;
    }
};

$scan = function (PinGate $gate, IuranScan $iuran) {
    if (! $this->unlocked || ! $this->sessionId) {
        return;
    }

    $session = \App\Models\RondaScanSession::find($this->sessionId);

    if (! $session || ! $session->isActive()) {
        $this->unlocked = false;
        $this->sessionId = null;
        $this->unlockError = 'PIN sudah kedaluwarsa.';

        return;
    }

    $result = $iuran->record($session, $this->token);

    $this->lastResult = [
        'paid' => $result->paid(),
        'status' => $result->status,
        'head' => $result->household?->head_name,
        'house' => $result->household?->house_number,
        'address' => $result->household?->address,
        'amount' => $result->transaction?->amount,
        'message' => $result->message,
    ];

    $this->reset('token');
};

?>

<x-layouts.public title="Scan Iuran">
    <div class="space-y-5">
        @unless ($unlocked)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h1 class="text-xl font-semibold text-slate-900">Scan Iuran Ronda</h1>
                <p class="mt-1 text-sm text-slate-600">Masukkan PIN harian dari pengurus untuk membuka mode scan.</p>

                <form wire:submit="unlock" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">PIN Harian</label>
                        <input wire:model="pin" type="text" inputmode="numeric" maxlength="6"
                               class="mt-1 w-full rounded-lg border-slate-300 text-center text-lg tracking-widest">
                        @error('pin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Buka Mode Scan</button>
                </form>

                @if ($unlockError)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">{{ $unlockError }}</p>
                @endif
            </div>
        @else
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-semibold text-slate-900">Scan Iuran</h1>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">PIN aktif</span>
                </div>
                <p class="mt-1 text-sm text-slate-600">Scan atau masukkan kode QR rumah untuk mencatat iuran Rp500.</p>

                <form wire:submit="scan" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Kode QR Rumah</label>
                        <input wire:model="token" type="text" autofocus
                               class="mt-1 w-full rounded-lg border-slate-300"
                               placeholder="Hasil scan akan terisi di sini">
                    </div>
                    <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Terima Cash Rp500</button>
                </form>
            </div>

            @if ($lastResult)
                @if ($lastResult['paid'])
                    <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
                        <p class="text-lg font-semibold text-emerald-800">Lunas Rp{{ number_format((int) $lastResult['amount'], 0, ',', '.') }}</p>
                        <p class="text-sm text-emerald-700">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                        @if ($lastResult['address']) <p class="text-xs text-emerald-600">{{ $lastResult['address'] }}</p> @endif
                    </div>
                @elseif ($lastResult['status'] === 'already_paid')
                    <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
                        <p class="font-semibold text-amber-800">{{ $lastResult['message'] }}</p>
                        <p class="text-sm text-amber-700">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                    </div>
                @else
                    <div class="rounded-2xl bg-red-50 p-5 ring-1 ring-red-200">
                        <p class="text-sm text-red-700">{{ $lastResult['message'] }}</p>
                    </div>
                @endif
            @endif
        @endunless

        <div class="text-center">
            <a href="{{ route('portal.home') }}" class="text-sm text-emerald-700 hover:underline">Kembali ke portal</a>
        </div>
    </div>
</x-layouts.public>
```

> Note: The QR code field is a plain text input so it stays testable. A browser QR scanner (e.g. `html5-qrcode`) can be layered on later as progressive enhancement by writing the decoded value into `token` via Alpine and calling `$wire.scan()`; the server flow does not change.

- [ ] **Step 5: Enable the portal home entry**

In `resources/views/livewire/portal/home.blade.php`, add a Scan Iuran entry for regu ronda:

```php
['label' => 'Scan Iuran (Petugas)', 'route' => 'portal.scan', 'desc' => 'Mode scan iuran ronda dengan PIN harian.', 'ready' => true],
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Kas/PublicScanIuranTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/portal tests/Feature/Kas/PublicScanIuranTest.php
git commit -m "feat: add public scan iuran page with pin gate"
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

- Login as Bendahara, open `Sesi Scan`, create a session for today with a window covering now, and note the generated PIN.
- Open a seeded household's QR page (Phase 02) and copy its `qr_token`.
- Open `/scan-iuran` (no login), enter the PIN, and confirm the scan mode unlocks.
- Paste the `qr_token`, submit, and confirm "Lunas Rp500" with the household name/address.
- Submit the same token again and confirm "Iuran rumah ini sudah tercatat hari ini."
- Enter an unknown token and confirm "QR rumah tidak dikenali."
- Expire the session window (or wait past `ends_at`) and confirm scanning reports the PIN as expired.
- Back in `Sesi Scan`, confirm the session's collected total reflects the recorded Rp500.
