# Phase 07 Pengumuman dan Laporan Warga Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build two warga-facing information modules. Pengumuman: pengurus publish RT announcements that warga read publicly with no login or phone. Laporan warga: warga submit reports using their registered phone number, and pengurus triage them through a follow-up status workflow. Public read needs nothing; public write requires a registered active phone.

**Architecture:** Continue the single Laravel 12 application. Two independent models: `Announcement` (title, body, published flag, published_at) and `Report` (phone, category, description, follow-up status, pengurus notes). Pengumuman public list shows only published rows ordered by publish date. Laporan submission reuses the Phase 03 `App\Services\ResidentLookup` to validate the nomor HP before storing, captures the normalized phone plus the matched resident, and is rate limited like other open write endpoints. Follow-up status is a typed enum with a fixed transition set, surfaced on a pengurus dashboard that audits every status change through `App\Support\Audit`.

**Tech Stack:** Laravel 12, PHP 8.3, MySQL 8, Livewire 3, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`pengurus` middleware, `Audit`, layouts), Phase 02 (`Resident`, `PhoneNumber`), and Phase 03 (`ResidentLookup`, `x-layouts.public`, `x-portal.phone-field`, rate-limit pattern).

---

## Prerequisites

- Phases 01–03 are complete: `pengurus` middleware, `App\Support\Audit`, `App\Models\Resident`, `App\Services\ResidentLookup`, `App\Support\PhoneNumber`, `x-layouts.app`, `x-layouts.public`, and `x-portal.phone-field` all exist.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `database/migrations/*_create_announcements_table.php`.
- Create: `app/Models/Announcement.php`.
- Create: `database/factories/AnnouncementFactory.php`.
- Create: `app/Enums/ReportStatus.php` (`baru`, `diproses`, `selesai`, `ditolak`).
- Create: `database/migrations/*_create_reports_table.php`.
- Create: `app/Models/Report.php`.
- Create: `database/factories/ReportFactory.php`.
- Modify: `routes/web.php` (public pengumuman + laporan routes, dashboard pengumuman + laporan routes).
- Create: `resources/views/livewire/dashboard/announcements/index.blade.php`.
- Create: `resources/views/livewire/dashboard/reports/index.blade.php`.
- Create: `resources/views/livewire/portal/announcements.blade.php`.
- Create: `resources/views/livewire/portal/report.blade.php`.
- Modify: `resources/views/livewire/portal/home.blade.php` (enable Pengumuman + Lapor Warga entries).
- Modify: `resources/views/components/layouts/app.blade.php` (add Pengumuman + Laporan nav links).
- Test: `tests/Feature/Announcements/AnnouncementModelTest.php`.
- Test: `tests/Feature/Announcements/AnnouncementManagementTest.php`.
- Test: `tests/Feature/Announcements/PublicAnnouncementTest.php`.
- Test: `tests/Feature/Reports/ReportModelTest.php`.
- Test: `tests/Feature/Reports/PublicReportSubmitTest.php`.
- Test: `tests/Feature/Reports/ReportManagementTest.php`.

## Task 1: Create the Announcement Model

**Files:**

- Create: `database/migrations/*_create_announcements_table.php`
- Create: `app/Models/Announcement.php`
- Create: `database/factories/AnnouncementFactory.php`
- Test: `tests/Feature/Announcements/AnnouncementModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Announcements/AnnouncementModelTest.php`:

```php
<?php

use App\Models\Announcement;

it('casts published flag and published_at', function () {
    $announcement = Announcement::factory()->create([
        'is_published' => true,
        'published_at' => '2026-06-01 08:00:00',
    ]);

    expect($announcement->fresh()->is_published)->toBeTrue();
    expect($announcement->published_at->format('Y-m-d'))->toBe('2026-06-01');
});

it('scopes only published announcements', function () {
    Announcement::factory()->create(['is_published' => true, 'published_at' => now()->subDay()]);
    Announcement::factory()->create(['is_published' => false, 'published_at' => null]);

    expect(Announcement::query()->published()->count())->toBe(1);
});

it('orders published announcements newest first', function () {
    $older = Announcement::factory()->create(['is_published' => true, 'published_at' => now()->subDays(2)]);
    $newer = Announcement::factory()->create(['is_published' => true, 'published_at' => now()->subDay()]);

    $ordered = Announcement::query()->published()->get();

    expect($ordered->first()->id)->toBe($newer->id);
    expect($ordered->last()->id)->toBe($older->id);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Announcements/AnnouncementModelTest.php
```

Expected: FAIL because the migration and model do not exist.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_announcements_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('announcements', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body');
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['is_published', 'published_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('announcements');
}
```

- [ ] **Step 4: Create the model**

Create `app/Models/Announcement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/AnnouncementFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_published' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }
}
```

- [ ] **Step 6: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Announcements/AnnouncementModelTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Announcement.php database/migrations database/factories/AnnouncementFactory.php tests/Feature/Announcements/AnnouncementModelTest.php
git commit -m "feat: add announcement model"
```

## Task 2: Build the Announcement Dashboard and Public View

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/announcements/index.blade.php`
- Create: `resources/views/livewire/portal/announcements.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Announcements/AnnouncementManagementTest.php`
- Test: `tests/Feature/Announcements/PublicAnnouncementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Announcements/AnnouncementManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from announcement management', function () {
    $this->get('/dashboard/pengumuman')->assertRedirect('/login');
});

it('creates an announcement as a draft and audits it', function () {
    $this->actingAs($this->admin);

    Volt::test('dashboard.announcements.index')
        ->set('title', 'Kerja Bakti')
        ->set('body', 'Minggu pagi jam 7.')
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->first();
    expect($announcement->title)->toBe('Kerja Bakti');
    expect($announcement->is_published)->toBeFalse();
    expect(AuditLog::query()->where('action', 'announcement.created')->exists())->toBeTrue();
});

it('publishes and unpublishes an announcement', function () {
    $announcement = Announcement::factory()->create(['is_published' => false]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.announcements.index')->call('togglePublish', $announcement->id);
    expect($announcement->fresh()->is_published)->toBeTrue();
    expect($announcement->fresh()->published_at)->not->toBeNull();

    Volt::test('dashboard.announcements.index')->call('togglePublish', $announcement->id);
    expect($announcement->fresh()->is_published)->toBeFalse();
});
```

- [ ] **Step 2: Write failing public tests**

Create `tests/Feature/Announcements/PublicAnnouncementTest.php`:

```php
<?php

use App\Models\Announcement;

it('shows published announcements publicly', function () {
    Announcement::factory()->published()->create(['title' => 'Jadwal Kerja Bakti']);

    $this->get('/pengumuman')
        ->assertOk()
        ->assertSee('Jadwal Kerja Bakti');
});

it('hides draft announcements from the public', function () {
    Announcement::factory()->create(['title' => 'Draft Rahasia', 'is_published' => false]);

    $this->get('/pengumuman')
        ->assertOk()
        ->assertDontSee('Draft Rahasia');
});
```

- [ ] **Step 3: Run failing tests**

```bash
php artisan test tests/Feature/Announcements/AnnouncementManagementTest.php tests/Feature/Announcements/PublicAnnouncementTest.php
```

Expected: FAIL because routes and views are missing.

- [ ] **Step 4: Add routes**

In `routes/web.php`, add the public route (outside the auth group) and the dashboard route (inside the `auth` + `pengurus` group):

```php
// public
Volt::route('/pengumuman', 'portal.announcements')->name('portal.announcements');

// pengurus
Volt::route('/dashboard/pengumuman', 'dashboard.announcements.index')->name('announcements.index');
```

- [ ] **Step 5: Create the dashboard page**

Create `resources/views/livewire/dashboard/announcements/index.blade.php`:

```blade
<?php

use App\Models\Announcement;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed};

state(['editingId' => null, 'title' => '', 'body' => '']);

rules([
    'title' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string', 'max:5000'],
]);

$announcements = computed(fn () => Announcement::query()->latest()->get());

$edit = function (int $id) {
    $a = Announcement::findOrFail($id);
    $this->editingId = $a->id;
    $this->title = $a->title;
    $this->body = $a->body;
};

$resetForm = fn () => $this->reset('editingId', 'title', 'body');

$save = function () {
    $data = $this->validate();

    if ($this->editingId) {
        $a = Announcement::findOrFail($this->editingId);
        $a->update($data);
        Audit::record(auth()->user(), 'announcement.updated', 'announcement', $a->id, ['title' => $a->title]);
    } else {
        $data['created_by'] = auth()->id();
        $a = Announcement::create($data);
        Audit::record(auth()->user(), 'announcement.created', 'announcement', $a->id, ['title' => $a->title]);
    }

    $this->resetForm();
};

$togglePublish = function (int $id) {
    $a = Announcement::findOrFail($id);
    $publish = ! $a->is_published;
    $a->update([
        'is_published' => $publish,
        'published_at' => $publish ? ($a->published_at ?? now()) : $a->published_at,
    ]);
    Audit::record(auth()->user(), $publish ? 'announcement.published' : 'announcement.unpublished', 'announcement', $a->id, []);
};

?>

<x-layouts.app title="Pengumuman">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Pengumuman</h1>

        <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div>
                <label class="block text-sm font-medium text-slate-700">Judul</label>
                <input wire:model="title" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Isi</label>
                <textarea wire:model="body" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
                    {{ $editingId ? 'Perbarui' : 'Simpan Draft' }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
                @endif
            </div>
        </form>

        <div class="space-y-3">
            @foreach ($this->announcements as $a)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-medium text-slate-900">{{ $a->title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ Str::limit($a->body, 140) }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $a->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $a->is_published ? 'Tampil' : 'Draft' }}
                        </span>
                    </div>
                    <div class="mt-3 flex gap-3 text-sm">
                        <button wire:click="edit({{ $a->id }})" class="text-slate-600 hover:underline">Edit</button>
                        <button wire:click="togglePublish({{ $a->id }})" class="text-emerald-700 hover:underline">
                            {{ $a->is_published ? 'Sembunyikan' : 'Tampilkan' }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Create the public page**

Create `resources/views/livewire/portal/announcements.blade.php`:

```blade
<?php

use App\Models\Announcement;
use function Livewire\Volt\{computed};

$announcements = computed(fn () => Announcement::query()->published()->take(50)->get());

?>

<x-layouts.public title="Pengumuman">
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-slate-900">Pengumuman RT</h1>

        @forelse ($this->announcements as $a)
            <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <h2 class="font-medium text-emerald-700">{{ $a->title }}</h2>
                <p class="text-xs text-slate-400">{{ $a->published_at->translatedFormat('d M Y') }}</p>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $a->body }}</p>
            </article>
        @empty
            <p class="text-slate-500">Belum ada pengumuman.</p>
        @endforelse

        <div class="text-center">
            <a href="{{ route('portal.home') }}" class="text-sm text-emerald-700 hover:underline">Kembali ke portal</a>
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 7: Wire nav and portal home**

In `resources/views/components/layouts/app.blade.php` nav, add:

```blade
<a href="{{ route('announcements.index') }}" class="text-slate-600 hover:text-slate-900">Pengumuman</a>
```

In `resources/views/livewire/portal/home.blade.php`, update the Pengumuman entry to `'route' => 'portal.announcements', 'ready' => true`.

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Announcements/AnnouncementManagementTest.php tests/Feature/Announcements/PublicAnnouncementTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/announcements resources/views/livewire/portal/announcements.blade.php resources/views/components/layouts/app.blade.php resources/views/livewire/portal/home.blade.php tests/Feature/Announcements
git commit -m "feat: add pengumuman dashboard and public view"
```

## Task 3: Create the Report Model

**Files:**

- Create: `app/Enums/ReportStatus.php`
- Create: `database/migrations/*_create_reports_table.php`
- Create: `app/Models/Report.php`
- Create: `database/factories/ReportFactory.php`
- Test: `tests/Feature/Reports/ReportModelTest.php`

- [ ] **Step 1: Write failing model tests**

Create `tests/Feature/Reports/ReportModelTest.php`:

```php
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\Resident;
use App\Models\Household;

it('casts status to enum and defaults to baru', function () {
    $report = Report::factory()->create(['status' => ReportStatus::BARU]);

    expect($report->fresh()->status)->toBe(ReportStatus::BARU);
});

it('optionally links to a resident', function () {
    $resident = Resident::factory()->for(Household::factory())->create();
    $report = Report::factory()->create(['resident_id' => $resident->id]);

    expect($report->resident->is($resident))->toBeTrue();
});

it('scopes open reports', function () {
    Report::factory()->create(['status' => ReportStatus::BARU]);
    Report::factory()->create(['status' => ReportStatus::DIPROSES]);
    Report::factory()->create(['status' => ReportStatus::SELESAI]);

    expect(Report::query()->open()->count())->toBe(2);
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Reports/ReportModelTest.php
```

Expected: FAIL because the enum, migration, and model do not exist.

- [ ] **Step 3: Create the status enum**

Create `app/Enums/ReportStatus.php`:

```php
<?php

namespace App\Enums;

enum ReportStatus: string
{
    case BARU = 'baru';
    case DIPROSES = 'diproses';
    case SELESAI = 'selesai';
    case DITOLAK = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::BARU => 'Baru',
            self::DIPROSES => 'Diproses',
            self::SELESAI => 'Selesai',
            self::DITOLAK => 'Ditolak',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::BARU, self::DIPROSES], true);
    }
}
```

- [ ] **Step 4: Create the migration**

```bash
php artisan make:migration create_reports_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('reports', function (Blueprint $table) {
        $table->id();
        $table->string('phone');
        $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
        $table->string('category');
        $table->text('description');
        $table->string('status')->default('baru');
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index('status');
        $table->index('phone');
    });
}

public function down(): void
{
    Schema::dropIfExists('reports');
}
```

- [ ] **Step 5: Create the model**

Create `app/Models/Report.php`:

```php
<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'resident_id',
        'category',
        'description',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return ['status' => ReportStatus::class];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::BARU->value, ReportStatus::DIPROSES->value]);
    }
}
```

- [ ] **Step 6: Create the factory**

Create `database/factories/ReportFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'phone' => '8'.fake()->unique()->numerify('##########'),
            'category' => fake()->randomElement(['Keamanan', 'Kebersihan', 'Fasilitas', 'Lainnya']),
            'description' => fake()->sentence(),
            'status' => ReportStatus::BARU,
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 7: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Reports/ReportModelTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/ReportStatus.php app/Models/Report.php database/migrations database/factories/ReportFactory.php tests/Feature/Reports/ReportModelTest.php
git commit -m "feat: add report model with follow-up status"
```

## Task 4: Build the Public Laporan Submission Page

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/portal/report.blade.php`
- Modify: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Reports/PublicReportSubmitTest.php`

- [ ] **Step 1: Write failing submission tests**

Create `tests/Feature/Reports/PublicReportSubmitTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Report;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-report:127.0.0.1');
    $this->household = Household::factory()->create();
});

it('serves the report page without login', function () {
    $this->get('/lapor')->assertOk()->assertSee('Lapor Warga');
});

it('stores a report for a registered active phone', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.report')
        ->set('phone', '0812-3456-7890')
        ->set('category', 'Keamanan')
        ->set('description', 'Lampu jalan mati di gang 3.')
        ->call('submit')
        ->assertSee('Laporan terkirim');

    $report = Report::query()->first();
    expect($report->phone)->toBe('81234567890');
    expect($report->resident_id)->toBe($resident->id);
});

it('rejects a report from an unregistered phone', function () {
    Volt::test('portal.report')
        ->set('phone', '0899-0000-0000')
        ->set('category', 'Keamanan')
        ->set('description', 'Test.')
        ->call('submit')
        ->assertSee('belum terdaftar');

    expect(Report::query()->count())->toBe(0);
});

it('validates required fields', function () {
    Resident::factory()->for($this->household)->create(['phone' => '81234567890', 'is_active' => true]);

    Volt::test('portal.report')
        ->set('phone', '81234567890')
        ->set('category', '')
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['category', 'description']);
});

it('rate limits repeated submissions', function () {
    $component = Volt::test('portal.report');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->set('category', 'X')->set('description', 'Y')->call('submit');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Reports/PublicReportSubmitTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the public route**

In `routes/web.php`, with the other public portal routes:

```php
Volt::route('/lapor', 'portal.report')->name('portal.report');
```

- [ ] **Step 4: Create the report page**

Create `resources/views/livewire/portal/report.blade.php`:

```blade
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Services\ResidentLookup;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules};

state([
    'phone' => '',
    'category' => '',
    'description' => '',
    'done' => false,
    'feedback' => null,
]);

rules([
    'phone' => ['required', 'string', 'max:30'],
    'category' => ['required', 'string', 'max:100'],
    'description' => ['required', 'string', 'min:5', 'max:2000'],
]);

$submit = function (ResidentLookup $lookup) {
    $this->validate();

    $key = 'portal-report:'.request()->ip();

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

    Report::create([
        'phone' => $this->phone,
        'resident_id' => $result->resident->id,
        'category' => $this->category,
        'description' => $this->description,
        'status' => ReportStatus::BARU,
    ]);

    $this->reset('phone', 'category', 'description');
    $this->done = true;
    $this->feedback = null;
};

?>

<x-layouts.public title="Lapor Warga">
    <div class="space-y-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Lapor Warga</h1>
            <p class="mt-1 text-sm text-slate-600">Sampaikan laporan Anda ke pengurus RT. Gunakan nomor HP yang terdaftar.</p>

            <form wire:submit="submit" class="mt-4 space-y-4">
                <x-portal.phone-field model="phone" />
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kategori</label>
                    <select wire:model="category" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">Pilih kategori</option>
                        <option>Keamanan</option>
                        <option>Kebersihan</option>
                        <option>Fasilitas</option>
                        <option>Lainnya</option>
                    </select>
                    @error('category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Deskripsi</label>
                    <textarea wire:model="description" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Kirim Laporan</button>
            </form>
        </div>

        @if ($done)
            <div class="rounded-2xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-200">
                <p class="text-lg font-semibold text-emerald-800">Laporan terkirim</p>
                <p class="mt-1 text-sm text-emerald-700">Terima kasih. Pengurus akan menindaklanjuti laporan Anda.</p>
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

In `resources/views/livewire/portal/home.blade.php`, update the Lapor Warga entry to `'route' => 'portal.report', 'ready' => true`.

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Reports/PublicReportSubmitTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/portal/report.blade.php resources/views/livewire/portal/home.blade.php tests/Feature/Reports/PublicReportSubmitTest.php
git commit -m "feat: add public laporan warga submission"
```

## Task 5: Build the Report Triage Dashboard

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/livewire/dashboard/reports/index.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/Reports/ReportManagementTest.php`

- [ ] **Step 1: Write failing management tests**

Create `tests/Feature/Reports/ReportManagementTest.php`:

```php
<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
});

it('blocks guests from report management', function () {
    $this->get('/dashboard/laporan')->assertRedirect('/login');
});

it('lists submitted reports for pengurus', function () {
    Report::factory()->create(['description' => 'Got mampet di blok C']);

    $this->actingAs($this->admin)
        ->get('/dashboard/laporan')
        ->assertOk()
        ->assertSee('Got mampet di blok C');
});

it('updates a report status with notes and audits it', function () {
    $report = Report::factory()->create(['status' => ReportStatus::BARU]);

    $this->actingAs($this->admin);

    Volt::test('dashboard.reports.index')
        ->call('startUpdate', $report->id)
        ->set('status', ReportStatus::SELESAI->value)
        ->set('notes', 'Sudah diperbaiki tim kebersihan.')
        ->call('saveUpdate')
        ->assertHasNoErrors();

    expect($report->fresh()->status)->toBe(ReportStatus::SELESAI);
    expect($report->fresh()->notes)->toBe('Sudah diperbaiki tim kebersihan.');
    expect(AuditLog::query()->where('action', 'report.status_changed')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Reports/ReportManagementTest.php
```

Expected: FAIL because the route and view are missing.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `auth` + `pengurus` group:

```php
Volt::route('/dashboard/laporan', 'dashboard.reports.index')->name('reports.index');
```

- [ ] **Step 4: Create the triage page**

Create `resources/views/livewire/dashboard/reports/index.blade.php`:

```blade
<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Support\Audit;
use function Livewire\Volt\{state, computed, rules};

state(['updateId' => null, 'status' => '', 'notes' => '', 'filter' => 'open']);

rules([
    'status' => ['required', 'in:baru,diproses,selesai,ditolak'],
    'notes' => ['nullable', 'string', 'max:2000'],
]);

$reports = computed(function () {
    $query = Report::query()->with('resident')->latest();

    if ($this->filter === 'open') {
        $query->open();
    }

    return $query->get();
});

$statuses = computed(fn () => ReportStatus::cases());

$startUpdate = function (int $id) {
    $report = Report::findOrFail($id);
    $this->updateId = $report->id;
    $this->status = $report->status->value;
    $this->notes = $report->notes ?? '';
};

$saveUpdate = function () {
    $this->validate();

    $report = Report::findOrFail($this->updateId);
    $from = $report->status->value;
    $report->update(['status' => $this->status, 'notes' => $this->notes]);

    Audit::record(auth()->user(), 'report.status_changed', 'report', $report->id, [
        'from' => $from,
        'to' => $this->status,
    ]);

    $this->reset('updateId', 'status', 'notes');
};

?>

<x-layouts.app title="Laporan Warga">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-900">Laporan Warga</h1>
            <select wire:model.live="filter" class="rounded-lg border-slate-300 text-sm">
                <option value="open">Belum selesai</option>
                <option value="all">Semua</option>
            </select>
        </div>

        <div class="space-y-3">
            @forelse ($this->reports as $report)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">{{ $report->category }}</p>
                            <p class="mt-1 text-slate-800">{{ $report->description }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ $report->resident?->name ?? 'Warga' }} · {{ $report->phone }} · {{ $report->created_at->format('d/m/Y H:i') }}
                            </p>
                            @if ($report->notes)
                                <p class="mt-2 rounded-lg bg-slate-50 p-2 text-sm text-slate-600">Catatan: {{ $report->notes }}</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $report->status->label() }}</span>
                    </div>

                    @if ($updateId === $report->id)
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
                        <button wire:click="startUpdate({{ $report->id }})" class="mt-3 text-sm text-emerald-700 hover:underline">Tindak lanjuti</button>
                    @endif
                </div>
            @empty
                <p class="text-slate-500">Tidak ada laporan.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Add the nav link**

In `resources/views/components/layouts/app.blade.php` nav, add:

```blade
<a href="{{ route('reports.index') }}" class="text-slate-600 hover:text-slate-900">Laporan</a>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Reports/ReportManagementTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/livewire/dashboard/reports resources/views/components/layouts/app.blade.php tests/Feature/Reports/ReportManagementTest.php
git commit -m "feat: add laporan warga triage dashboard"
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

- Login as pengurus, open `Pengumuman`, create a draft, then publish it.
- Open `/pengumuman` (no login) and confirm the published item appears but drafts do not.
- Open `/lapor`, submit a report with a seeded resident's phone, and confirm "Laporan terkirim".
- Submit with an unregistered phone and confirm the "belum terdaftar" message.
- Back in the dashboard `Laporan`, confirm the report appears, change its status to Selesai with a note, and verify the filter hides it under "Belum selesai".
- Confirm `audit_logs` has `announcement.published` and `report.status_changed` entries.
