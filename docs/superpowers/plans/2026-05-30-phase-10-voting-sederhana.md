# Phase 10 Voting Sederhana Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the simple voting module and complete the MVP. Pengurus create a voting with a question, options, and an active period. Warga vote from the public portal using their registered phone number, with one vote per phone per voting enforced. Results are visible to pengurus always and to warga after they vote or once the voting closes.

**Architecture:** Continue the single Laravel 12 application. Three models: `Vote` (question, period, status), `VoteOption` (belongs to a vote), and `VoteBallot` (one row per phone per vote, recording the chosen option). Casting a ballot reuses the Phase 03 `App\Services\ResidentLookup` to validate the nomor HP and a new `App\Services\VotingService` that enforces the active period, the registered-active-phone rule, and one-vote-per-phone via a unique constraint plus a guarded insert. Pengurus voting management is login-gated and audited; the public vote page is rate limited. Tallies are computed with a grouped count query so results scale without per-ballot iteration. This is the final MVP module; a closing verification step confirms the full test suite is green.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 02 (`Resident`, `PhoneNumber`), and Phase 03 (`ResidentLookup`, `x-layouts.public`, `x-portal.phone-field`, rate-limit pattern).

---

## Prerequisites

- Phases 01–03 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\Resident`, `App\Services\ResidentLookup`, `App\Support\PhoneNumber`, `x-layouts.app`, `x-layouts.public`, `x-portal.phone-field`, and the `portal.home` service grid all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `app/Enums/VoteStatus.php` (`draft`, `aktif`, `selesai`).
- Create: `database/migrations/*_create_votes_table.php`.
- Create: `database/migrations/*_create_vote_options_table.php`.
- Create: `database/migrations/*_create_vote_ballots_table.php`.
- Create: `app/Models/Vote.php`, `app/Models/VoteOption.php`, `app/Models/VoteBallot.php`.
- Create: `database/factories/VoteFactory.php`, `database/factories/VoteOptionFactory.php`.
- Create: `app/Services/BallotResult.php` and `app/Services/VotingService.php`.
- Modify: `routes/web.php` (public voting list/detail, dashboard voting routes).
- Create: `resources/views/livewire/dashboard/votes/index.blade.php` and `.../votes/show.blade.php`.
- Create: `resources/views/livewire/portal/votes.blade.php` and `.../portal/vote.blade.php`.
- Modify: `resources/views/livewire/portal/home.blade.php` (add Voting entry).
- Modify: `resources/views/components/layouts/app.blade.php` (add Voting nav link).
- Test: `tests/Feature/Votes/VoteModelTest.php`.
- Test: `tests/Feature/Votes/VotingServiceTest.php`.
- Test: `tests/Feature/Votes/VoteManagementTest.php`.
- Test: `tests/Feature/Votes/PublicVoteTest.php`.

## Task 1: Create the Vote, Option, and Ballot Models

**Files:**

- Create: `app/Enums/VoteStatus.php`
- Create: `database/migrations/*_create_votes_table.php`
- Create: `database/migrations/*_create_vote_options_table.php`
- Create: `database/migrations/*_create_vote_ballots_table.php`
- Create: `app/Models/Vote.php`, `app/Models/VoteOption.php`, `app/Models/VoteBallot.php`
- Create: `database/factories/VoteFactory.php`, `database/factories/VoteOptionFactory.php`
- Test: `tests/Feature/Votes/VoteModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Votes/VoteModelTest.php`:

```php
<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use App\Models\VoteOption;

it('casts status and period dates', function () {
    $vote = Vote::factory()->create([
        'status' => VoteStatus::AKTIF,
        'starts_at' => '2026-06-01',
        'ends_at' => '2026-06-07',
    ]);

    expect($vote->fresh()->status)->toBe(VoteStatus::AKTIF);
    expect($vote->starts_at->toDateString())->toBe('2026-06-01');
});

it('has many options', function () {
    $vote = Vote::factory()->create();
    VoteOption::factory()->count(3)->for($vote)->create();

    expect($vote->options)->toHaveCount(3);
});

it('reports open only when active and within the period', function () {
    $open = Vote::factory()->create([
        'status' => VoteStatus::AKTIF,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    $closed = Vote::factory()->create([
        'status' => VoteStatus::AKTIF,
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDay(),
    ]);

    expect($open->isOpen())->toBeTrue();
    expect($closed->isOpen())->toBeFalse();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Votes/VoteModelTest.php
```

Expected: FAIL because the enum, migrations, and models do not exist.

- [ ] **Step 3: Create the status enum**

Create `app/Enums/VoteStatus.php`:

```php
<?php

namespace App\Enums;

enum VoteStatus: string
{
    case DRAFT = 'draft';
    case AKTIF = 'aktif';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::AKTIF => 'Aktif',
            self::SELESAI => 'Selesai',
        };
    }
}
```

- [ ] **Step 4: Create the migrations**

```bash
php artisan make:migration create_votes_table
php artisan make:migration create_vote_options_table
php artisan make:migration create_vote_ballots_table
```

`votes` body:

```php
public function up(): void
{
    Schema::create('votes', function (Blueprint $table) {
        $table->id();
        $table->string('question');
        $table->string('status')->default('draft');
        $table->date('starts_at')->nullable();
        $table->date('ends_at')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index('status');
    });
}

public function down(): void
{
    Schema::dropIfExists('votes');
}
```

`vote_options` body:

```php
public function up(): void
{
    Schema::create('vote_options', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
        $table->string('label');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('vote_options');
}
```

`vote_ballots` body:

```php
public function up(): void
{
    Schema::create('vote_ballots', function (Blueprint $table) {
        $table->id();
        $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
        $table->foreignId('vote_option_id')->constrained()->cascadeOnDelete();
        $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
        $table->string('phone');
        $table->timestamps();

        $table->unique(['vote_id', 'phone']);
    });
}

public function down(): void
{
    Schema::dropIfExists('vote_ballots');
}
```

- [ ] **Step 5: Create the models**

Create `app/Models/Vote.php`:

```php
<?php

namespace App\Models;

use App\Enums\VoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VoteStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }

    public function isOpen(): bool
    {
        if ($this->status !== VoteStatus::AKTIF) {
            return false;
        }

        $now = now();

        return (! $this->starts_at || $now->gte($this->starts_at->startOfDay()))
            && (! $this->ends_at || $now->lte($this->ends_at->endOfDay()));
    }
}
```

Create `app/Models/VoteOption.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoteOption extends Model
{
    use HasFactory;

    protected $fillable = ['vote_id', 'label'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }
}
```

Create `app/Models/VoteBallot.php`:

```php
<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteBallot extends Model
{
    use HasFactory;

    protected $fillable = ['vote_id', 'vote_option_id', 'resident_id', 'phone'];

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(VoteOption::class, 'vote_option_id');
    }
}
```

- [ ] **Step 6: Create the factories**

Create `database/factories/VoteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\VoteStatus;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'status' => VoteStatus::DRAFT,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => VoteStatus::AKTIF,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
        ]);
    }
}
```

Create `database/factories/VoteOptionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Vote;
use App\Models\VoteOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteOptionFactory extends Factory
{
    protected $model = VoteOption::class;

    public function definition(): array
    {
        return [
            'vote_id' => Vote::factory(),
            'label' => fake()->words(2, true),
        ];
    }
}
```

- [ ] **Step 7: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Votes/VoteModelTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/VoteStatus.php app/Models/Vote.php app/Models/VoteOption.php app/Models/VoteBallot.php database/migrations database/factories/VoteFactory.php database/factories/VoteOptionFactory.php tests/Feature/Votes/VoteModelTest.php
git commit -m "feat: add vote, option, and ballot models"
```

## Task 2: Build the Voting Service

**Files:**

- Create: `app/Services/BallotResult.php`
- Create: `app/Services/VotingService.php`
- Test: `tests/Feature/Votes/VotingServiceTest.php`

- [ ] **Step 1: Write failing service tests**

Create `tests/Feature/Votes/VotingServiceTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use App\Services\VotingService;

beforeEach(function () {
    $this->service = app(VotingService::class);
    $this->vote = Vote::factory()->open()->create();
    $this->option = VoteOption::factory()->for($this->vote)->create();
    $this->household = Household::factory()->create();
});

it('casts a ballot for a registered active phone', function () {
    $resident = Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $result = $this->service->cast($this->vote, $this->option->id, '0812-3456-7890');

    expect($result->success())->toBeTrue();
    expect(VoteBallot::query()->count())->toBe(1);
    expect(VoteBallot::query()->first()->resident_id)->toBe($resident->id);
});

it('rejects an unregistered phone', function () {
    $result = $this->service->cast($this->vote, $this->option->id, '0899-0000-0000');

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('rejects a second vote from the same phone', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $this->service->cast($this->vote, $this->option->id, '81234567890');
    $result = $this->service->cast($this->vote, $this->option->id, '081234567890');

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Nomor HP ini sudah memberikan suara.');
    expect(VoteBallot::query()->count())->toBe(1);
});

it('rejects voting when the vote is closed', function () {
    $closed = Vote::factory()->create(['status' => \App\Enums\VoteStatus::SELESAI]);
    $option = VoteOption::factory()->for($closed)->create();
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $result = $this->service->cast($closed, $option->id, '81234567890');

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Voting sudah ditutup.');
});

it('rejects an option that does not belong to the vote', function () {
    $other = VoteOption::factory()->create();
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $result = $this->service->cast($this->vote, $other->id, '81234567890');

    expect($result->success())->toBeFalse();
    expect($result->message)->toBe('Pilihan tidak valid.');
});

it('tallies results grouped by option', function () {
    $b = VoteOption::factory()->for($this->vote)->create();
    Resident::factory()->for($this->household)->create(['phone' => '81111111111', 'is_active' => true]);
    Resident::factory()->for($this->household)->create(['phone' => '82222222222', 'is_active' => true]);

    $this->service->cast($this->vote, $this->option->id, '81111111111');
    $this->service->cast($this->vote, $b->id, '82222222222');

    $tally = $this->service->tally($this->vote);

    expect($tally[$this->option->id])->toBe(1);
    expect($tally[$b->id])->toBe(1);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Votes/VotingServiceTest.php
```

Expected: FAIL because the service classes do not exist.

- [ ] **Step 3: Create the result object**

Create `app/Services/BallotResult.php`:

```php
<?php

namespace App\Services;

use App\Models\VoteBallot;

class BallotResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?VoteBallot $ballot = null,
        public readonly ?string $message = null,
    ) {}

    public static function done(VoteBallot $ballot): self
    {
        return new self(ok: true, ballot: $ballot);
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

- [ ] **Step 4: Create the voting service**

Create `app/Services/VotingService.php`:

```php
<?php

namespace App\Services;

use App\Models\Vote;
use App\Models\VoteBallot;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;

class VotingService
{
    public function __construct(
        protected ResidentLookup $lookup,
    ) {}

    public function cast(Vote $vote, int $optionId, ?string $rawPhone): BallotResult
    {
        if (! $vote->isOpen()) {
            return BallotResult::fail('Voting sudah ditutup.');
        }

        if (! $vote->options()->whereKey($optionId)->exists()) {
            return BallotResult::fail('Pilihan tidak valid.');
        }

        $lookup = $this->lookup->resolve($rawPhone);

        if (! $lookup->found()) {
            return BallotResult::fail($lookup->message);
        }

        $phone = PhoneNumber::normalize($rawPhone);

        if ($vote->ballots()->where('phone', $phone)->exists()) {
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        try {
            $ballot = $vote->ballots()->create([
                'vote_option_id' => $optionId,
                'resident_id' => $lookup->resident->id,
                'phone' => $phone,
            ]);
        } catch (QueryException) {
            // Unique (vote_id, phone) raced with a concurrent insert.
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        return BallotResult::done($ballot);
    }

    public function tally(Vote $vote): array
    {
        return $vote->ballots()
            ->selectRaw('vote_option_id, COUNT(*) as total')
            ->groupBy('vote_option_id')
            ->pluck('total', 'vote_option_id')
            ->toArray();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Votes/VotingServiceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/BallotResult.php app/Services/VotingService.php tests/Feature/Votes/VotingServiceTest.php
git commit -m "feat: add voting service with one-vote-per-phone"
```

## Task 3: Build the Voting Management Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/votes/index.blade.php`
- Create: `resources/views/livewire/dashboard/votes/show.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Votes/VoteManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Votes/VoteManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Enums\VoteStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteOption;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from voting management', function () {
    $this->get('/dashboard/voting')->assertRedirect('/login');
});

it('creates a vote with options and audits it', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.votes.index')
        ->set('question', 'Setuju iuran kebersihan naik?')
        ->set('optionsText', "Setuju\nTidak Setuju")
        ->set('starts_at', today()->toDateString())
        ->set('ends_at', today()->addDays(3)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $vote = Vote::query()->first();
    expect($vote->question)->toBe('Setuju iuran kebersihan naik?');
    expect($vote->options()->count())->toBe(2);
    expect(AuditLog::query()->where('action', 'vote.created')->exists())->toBeTrue();
});

it('activates and closes a vote', function () {
    $vote = Vote::factory()->create(['status' => VoteStatus::DRAFT]);
    VoteOption::factory()->count(2)->for($vote)->create();

    $this->actingAs($this->admin);

    Volt::test('dashboard.votes.index')->call('activate', $vote->id);
    expect($vote->fresh()->status)->toBe(VoteStatus::AKTIF);

    Volt::test('dashboard.votes.index')->call('close', $vote->id);
    expect($vote->fresh()->status)->toBe(VoteStatus::SELESAI);
});

it('shows tally on the detail page', function () {
    $vote = Vote::factory()->open()->create();
    VoteOption::factory()->for($vote)->create(['label' => 'Setuju']);

    $this->actingAs($this->admin)
        ->get('/dashboard/voting/'.$vote->id)
        ->assertOk()
        ->assertSee('Setuju');
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Votes/VoteManagementTest.php
```

Expected: FAIL because routes and views are missing.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/voting', 'dashboard.votes.index')->name('votes.index');
Volt::route('/dashboard/voting/{vote}', 'dashboard.votes.show')->name('votes.show');
```

- [ ] **Step 4: Create the management list page**

Create `resources/views/livewire/dashboard/votes/index.blade.php`:

```blade
<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use App\Support\Audit;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, rules, computed};

state(['question' => '', 'optionsText' => '', 'starts_at' => '', 'ends_at' => '']);

rules([
    'question' => ['required', 'string', 'max:255'],
    'optionsText' => ['required', 'string'],
    'starts_at' => ['nullable', 'date'],
    'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
]);

$votes = computed(fn () => Vote::query()->withCount('ballots')->latest()->get());

$save = function () {
    $this->validate();

    $options = collect(preg_split('/\r\n|\r|\n/', $this->optionsText))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();

    if ($options->count() < 2) {
        $this->addError('optionsText', 'Minimal dua pilihan.');

        return;
    }

    $vote = Vote::create([
        'question' => $this->question,
        'status' => VoteStatus::DRAFT,
        'starts_at' => $this->starts_at ?: null,
        'ends_at' => $this->ends_at ?: null,
        'created_by' => auth()->id(),
    ]);

    $options->each(fn ($label) => $vote->options()->create(['label' => $label]));

    Audit::record(auth()->user(), 'vote.created', 'vote', $vote->id, ['question' => $vote->question]);

    $this->reset('question', 'optionsText', 'starts_at', 'ends_at');
};

$activate = function (int $id) {
    $vote = Vote::findOrFail($id);
    $vote->update(['status' => VoteStatus::AKTIF]);
    Audit::record(auth()->user(), 'vote.activated', 'vote', $vote->id, []);
};

$close = function (int $id) {
    $vote = Vote::findOrFail($id);
    $vote->update(['status' => VoteStatus::SELESAI]);
    Audit::record(auth()->user(), 'vote.closed', 'vote', $vote->id, []);
};

?>

<x-layouts.app title="Voting">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Voting</h1>

        <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="block text-sm font-medium text-slate-700">Pertanyaan</label>
                <input wire:model="question" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('question') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Pilihan (satu per baris)</label>
                <textarea wire:model="optionsText" rows="3" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Setuju&#10;Tidak Setuju"></textarea>
                @error('optionsText') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Mulai</label>
                    <input wire:model="starts_at" type="date" class="mt-1 w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Selesai</label>
                    <input wire:model="ends_at" type="date" class="mt-1 w-full rounded-lg border-slate-300">
                    @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Simpan Draft</button>
        </form>

        <div class="space-y-3">
            @foreach ($this->votes as $vote)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <a href="{{ route('votes.show', $vote) }}" class="font-medium text-emerald-700 hover:underline">{{ $vote->question }}</a>
                            <p class="mt-1 text-xs text-slate-400">{{ $vote->ballots_count }} suara · {{ $vote->status->label() }}</p>
                        </div>
                        <div class="flex gap-3 text-sm">
                            @if ($vote->status === \App\Enums\VoteStatus::DRAFT)
                                <button wire:click="activate({{ $vote->id }})" class="text-emerald-700 hover:underline">Aktifkan</button>
                            @elseif ($vote->status === \App\Enums\VoteStatus::AKTIF)
                                <button wire:click="close({{ $vote->id }})" class="text-red-600 hover:underline">Tutup</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Create the detail/tally page**

Create `resources/views/livewire/dashboard/votes/show.blade.php`:

```blade
<?php

use App\Models\Vote;
use App\Services\VotingService;
use function Livewire\Volt\{state, computed, mount};

state(['vote' => null]);

mount(function (Vote $vote) {
    $this->vote = $vote->load('options');
});

$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));

?>

<x-layouts.app title="Hasil Voting">
    <div class="space-y-6">
        <div>
            <a href="{{ route('votes.index') }}" class="text-sm text-emerald-700 hover:underline">&larr; Voting</a>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ $vote->question }}</h1>
            <p class="text-sm text-slate-500">{{ $vote->status->label() }} · {{ $this->total }} suara</p>
        </div>

        <div class="space-y-3">
            @foreach ($vote->options as $option)
                @php($count = $this->tally[$option->id] ?? 0)
                @php($pct = $this->total > 0 ? round($count / $this->total * 100) : 0)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-800">{{ $option->label }}</span>
                        <span class="text-slate-500">{{ $count }} ({{ $pct }}%)</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Add the nav link**

In `resources/views/components/layouts/app.blade.php` nav, add:

```blade
<a href="{{ route('votes.index') }}" class="text-slate-600 hover:text-slate-900">Voting</a>
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Votes/VoteManagementTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/votes resources/views/components/layouts/app.blade.php tests/Feature/Votes/VoteManagementTest.php
git commit -m "feat: add voting management dashboard"
```

## Task 4: Build the Public Voting Pages

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/portal/votes.blade.php`
- Create: `resources/views/livewire/portal/vote.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Votes/PublicVoteTest.php`

- [ ] **Step 1: Write failing public tests**

Create `tests/Feature/Votes/PublicVoteTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-vote:127.0.0.1');
    $this->household = Household::factory()->create();
    $this->vote = Vote::factory()->open()->create();
    $this->option = VoteOption::factory()->for($this->vote)->create(['label' => 'Setuju']);
});

it('lists active votes publicly', function () {
    $this->get('/voting')->assertOk()->assertSee($this->vote->question);
});

it('casts a vote from a registered phone', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    Volt::test('portal.vote', ['vote' => $this->vote])
        ->set('phone', '0812-3456-7890')
        ->set('optionId', $this->option->id)
        ->call('submit')
        ->assertSee('Suara Anda tercatat');

    expect(VoteBallot::query()->count())->toBe(1);
});

it('rejects an unregistered phone', function () {
    Volt::test('portal.vote', ['vote' => $this->vote])
        ->set('phone', '0899-0000-0000')
        ->set('optionId', $this->option->id)
        ->call('submit')
        ->assertSee('belum terdaftar');

    expect(VoteBallot::query()->count())->toBe(0);
});

it('rejects a duplicate vote', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    $component = Volt::test('portal.vote', ['vote' => $this->vote]);
    $component->set('phone', '81234567890')->set('optionId', $this->option->id)->call('submit');
    $component->set('phone', '081234567890')->set('optionId', $this->option->id)->call('submit')
        ->assertSee('sudah memberikan suara');

    expect(VoteBallot::query()->count())->toBe(1);
});

it('rate limits repeated vote attempts', function () {
    $component = Volt::test('portal.vote', ['vote' => $this->vote]);

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->set('optionId', $this->option->id)->call('submit');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Votes/PublicVoteTest.php
```

Expected: FAIL because public routes and views are missing.

- [ ] **Step 3: Add the public routes**

In `routes/web.php`, with the other public portal routes:

```php
Volt::route('/voting', 'portal.votes')->name('portal.votes');
Volt::route('/voting/{vote}', 'portal.vote')->name('portal.vote');
```

- [ ] **Step 4: Create the public list page**

Create `resources/views/livewire/portal/votes.blade.php`:

```blade
<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use function Livewire\Volt\{computed};

$votes = computed(fn () => Vote::query()
    ->whereIn('status', [VoteStatus::AKTIF->value, VoteStatus::SELESAI->value])
    ->latest()
    ->take(20)
    ->get());

?>

<x-layouts.public title="Voting Warga">
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-slate-900">Voting Warga</h1>

        @forelse ($this->votes as $vote)
            <a href="{{ route('portal.vote', $vote) }}" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">
                <p class="font-medium text-slate-900">{{ $vote->question }}</p>
                <p class="mt-1 text-xs {{ $vote->isOpen() ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $vote->isOpen() ? 'Sedang berlangsung' : 'Sudah ditutup' }}
                </p>
            </a>
        @empty
            <p class="text-slate-500">Belum ada voting.</p>
        @endforelse

        <div class="text-center">
            <a href="{{ route('portal.home') }}" class="text-sm text-emerald-700 hover:underline">Kembali ke portal</a>
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 5: Create the public vote page**

Create `resources/views/livewire/portal/vote.blade.php`:

```blade
<?php

use App\Models\Vote;
use App\Services\VotingService;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules, computed, mount};

state(['vote' => null, 'phone' => '', 'optionId' => null, 'done' => false, 'feedback' => null]);

mount(function (Vote $vote) {
    $this->vote = $vote->load('options');
});

rules([
    'phone' => ['required', 'string', 'max:30'],
    'optionId' => ['required', 'integer'],
]);

$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));
$showResults = computed(fn () => $this->done || ! $this->vote->isOpen());

$submit = function (VotingService $voting) {
    $this->validate();

    $key = 'portal-vote:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $voting->cast($this->vote, (int) $this->optionId, $this->phone);

    if ($result->success()) {
        $this->done = true;
        $this->feedback = null;
        $this->reset('phone', 'optionId');
    } else {
        $this->done = false;
        $this->feedback = $result->message;
    }
};

?>

<x-layouts.public title="Voting">
    <div class="space-y-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">{{ $vote->question }}</h1>
            <p class="mt-1 text-xs {{ $vote->isOpen() ? 'text-emerald-600' : 'text-slate-400' }}">
                {{ $vote->isOpen() ? 'Sedang berlangsung' : 'Sudah ditutup' }}
            </p>

            @if ($this->showResults)
                <div class="mt-4 space-y-3">
                    @foreach ($vote->options as $option)
                        @php($count = $this->tally[$option->id] ?? 0)
                        @php($pct = $this->total > 0 ? round($count / $this->total * 100) : 0)
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-700">{{ $option->label }}</span>
                                <span class="text-slate-500">{{ $count }} ({{ $pct }}%)</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($vote->isOpen())
                <form wire:submit="submit" class="mt-4 space-y-4">
                    <x-portal.phone-field model="phone" />
                    <div class="space-y-2">
                        @foreach ($vote->options as $option)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                <input wire:model="optionId" type="radio" value="{{ $option->id }}" class="text-emerald-600">
                                <span class="text-slate-800">{{ $option->label }}</span>
                            </label>
                        @endforeach
                        @error('optionId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Kirim Suara</button>
                </form>
            @endif
        </div>

        @if ($done)
            <div class="rounded-2xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-200">
                <p class="text-lg font-semibold text-emerald-800">Suara Anda tercatat</p>
                <p class="mt-1 text-sm text-emerald-700">Terima kasih sudah berpartisipasi.</p>
            </div>
        @elseif ($feedback)
            <div class="rounded-2xl bg-amber-50 p-5 text-center ring-1 ring-amber-200">
                <p class="text-sm text-amber-700">{{ $feedback }}</p>
            </div>
        @endif

        <div class="text-center">
            <a href="{{ route('portal.votes') }}" class="text-sm text-emerald-700 hover:underline">Lihat voting lain</a>
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 6: Enable the portal home entry**

In `resources/views/livewire/portal/home.blade.php`, add a Voting entry:

```php
['label' => 'Voting', 'route' => 'portal.votes', 'desc' => 'Ikut voting warga RT.', 'ready' => true],
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Votes/PublicVoteTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php resources/views/livewire/portal/votes.blade.php resources/views/livewire/portal/vote.blade.php resources/views/livewire/portal/home.blade.php tests/Feature/Votes/PublicVoteTest.php
git commit -m "feat: add public voting pages"
```

## Final Verification (MVP Complete)

- [ ] Run the entire suite and build:

```bash
php artisan test
npm run build
```

Expected: all tests across Phases 01–10 pass and assets build.

- [ ] Manual smoke test:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

- Login as pengurus, open `Voting`, create a vote with two options, set the period, and activate it.
- Open `/voting` (no login), open the vote, submit with a seeded resident's phone, and confirm "Suara Anda tercatat" plus the results bar.
- Try voting again with the same number and confirm "sudah memberikan suara".
- Try an unregistered number and confirm the "belum terdaftar" message.
- Close the vote from the dashboard and confirm the public page now shows results without the voting form.
- Confirm the dashboard detail page tally matches the public results.

- [ ] MVP review checklist (maps to the design spec testing list):
    - Admin login + manage warga/rumah (Phase 02).
    - Public read of pengumuman + jadwal ronda (Phases 04, 07).
    - Unregistered phone rejected for official actions (Phases 03–10).
    - PIN-gated scan iuran Rp500, once per rumah per date (Phase 05).
    - Check-in ronda by scheduled phone, denda Rp5.000 after review (Phases 04, 06).
    - Kas rekap harian/mingguan/bulanan (Phase 06).
    - One vote per registered phone (Phase 10).
