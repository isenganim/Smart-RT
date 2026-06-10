# Admin Operational Command Center Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the shared responsive admin shell and an operational dashboard that prioritizes actionable RT work, follows `DESIGN.md`, and works completely on desktop and mobile.

**Architecture:** Add a focused `AdminDashboardSummary` service for dashboard queries, then compose the UI from small Blade components owned by `resources/views/components/admin`. Replace the horizontal chip navigation with a desktop sidebar and a five-item mobile bottom bar whose `Lainnya` button opens an Alpine.js drawer. Keep Laravel Livewire/Volt, existing routes, authorization, audit behavior, and all portal files unchanged.

**Tech Stack:** Laravel 12, PHP 8.4, Livewire 4, Volt, Alpine.js 3, Tailwind CSS 4, Pest 4, DDEV, Playwright.

---

## Scope And File Map

This plan implements Phase 1 from
`docs/superpowers/specs/2026-06-10-admin-operational-command-center-design.md`.
Later plans will migrate the individual admin modules onto the components
created here.

**Create:**

- `app/Services/AdminDashboardSummary.php` - dashboard metrics, action queue,
  30-day cash trend, and recent activity.
- `resources/views/components/admin/page-header.blade.php` - consistent page
  title, description, and action area.
- `resources/views/components/admin/button.blade.php` - primary, secondary,
  ghost, and danger action variants.
- `resources/views/components/admin/panel.blade.php` - standard light admin
  surface.
- `resources/views/components/admin/metric.blade.php` - summary metric block.
- `resources/views/components/admin/status-badge.blade.php` - semantic status
  treatment using text and color.
- `resources/views/components/admin/empty-state.blade.php` - compact empty
  state with optional action.
- `tests/Feature/Dashboard/AdminDashboardSummaryTest.php` - service behavior.
- `tests/Feature/Dashboard/AdminShellTest.php` - rendered shell semantics and
  navigation contract.

**Modify:**

- `resources/css/app.css` - shared admin focus, reduced-motion, and safe-area
  utilities.
- `resources/views/components/layouts/app.blade.php` - responsive admin shell.
- `resources/views/livewire/auth/logout-button.blade.php` - light-shell logout
  styling and accessible loading state.
- `resources/views/livewire/dashboard/index.blade.php` - operational command
  center dashboard.
- `tests/Feature/Dashboard/DashboardAccessTest.php` - dashboard content
  assertions.

**Do not modify:**

- `resources/views/components/layouts/public.blade.php`
- `resources/views/components/portal/**`
- Existing business services other than consuming `KasReport`.
- Existing uncommitted user changes outside the files listed above.

### Task 1: Establish The Dashboard Summary Contract

**Files:**

- Create: `tests/Feature/Dashboard/AdminDashboardSummaryTest.php`
- Create: `app/Services/AdminDashboardSummary.php`

- [ ] **Step 1: Write the failing summary service test**

```php
<?php

use App\Enums\LetterStatus;
use App\Enums\ReportStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
use App\Services\AdminDashboardSummary;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-11 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('builds truthful dashboard metrics actions trend and activity', function () {
    $actor = User::factory()->create(['name' => 'Ketua RT']);
    $paid = Household::factory()->create(['is_active' => true]);
    $unpaid = Household::factory()->create(['is_active' => true]);
    Household::factory()->inactive()->create();
    Resident::factory()->for($paid)->create(['is_active' => true]);
    Resident::factory()->for($unpaid)->create(['is_active' => false]);

    CashTransaction::factory()->for($paid)->create([
        'date' => today(),
        'type' => TransactionType::IURAN_HARIAN,
        'amount' => 500,
    ]);
    CashTransaction::factory()->for($paid)->create([
        'date' => today()->subDay(),
        'type' => TransactionType::DENDA,
        'amount' => 5000,
    ]);
    CashTransaction::factory()->for($paid)->create([
        'date' => today(),
        'amount' => 9000,
        'cancelled_at' => now(),
        'reason' => 'Salah input',
    ]);

    Report::factory()->create(['status' => ReportStatus::BARU]);
    Report::factory()->create(['status' => ReportStatus::SELESAI]);
    LetterRequest::factory()->create(['status' => LetterStatus::DIAJUKAN]);
    LetterRequest::factory()->create(['status' => LetterStatus::SELESAI]);

    $schedule = RondaSchedule::factory()->create(['date' => today()]);
    RondaAssignment::factory()->for($schedule)->create(['checked_in_at' => null]);

    AuditLog::query()->create([
        'actor_id' => $actor->id,
        'action' => 'household.created',
        'subject_type' => 'household',
        'subject_id' => $paid->id,
        'metadata' => [],
    ]);

    $summary = app(AdminDashboardSummary::class)->forDate(today());

    expect($summary['metrics'])->toMatchArray([
        'households' => 2,
        'residents' => 1,
        'month_cash' => 5500,
        'action_count' => 4,
    ]);
    expect(collect($summary['actions'])->pluck('count', 'key')->all())
        ->toMatchArray([
            'unpaid_households' => 1,
            'open_reports' => 1,
            'pending_letters' => 1,
            'missing_checkins' => 1,
        ]);
    expect($summary['cash_trend'])->toHaveCount(30);
    expect(collect($summary['cash_trend'])->last()['total'])->toBe(500);
    expect($summary['recent_activity'])->first()->toMatchArray([
        'label' => 'Rumah/KK ditambahkan',
        'actor' => 'Ketua RT',
    ]);
});
```

- [ ] **Step 2: Run the test and verify the missing class failure**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/AdminDashboardSummaryTest.php
```

Expected: FAIL because `App\Services\AdminDashboardSummary` does not exist.

- [ ] **Step 3: Implement the dashboard summary service**

```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardSummary
{
    public function __construct(private readonly KasReport $kasReport)
    {
    }

    public function forDate(CarbonInterface $date): array
    {
        $reference = Carbon::instance($date)->startOfDay();
        $actions = $this->actions($reference);

        return [
            'metrics' => [
                'households' => Household::query()->where('is_active', true)->count(),
                'residents' => Resident::query()->where('is_active', true)->count(),
                'month_cash' => $this->kasReport->rangeTotal(
                    $reference->copy()->startOfMonth(),
                    $reference->copy()->endOfMonth(),
                ),
                'action_count' => collect($actions)->sum('count'),
            ],
            'actions' => $actions,
            'cash_trend' => $this->cashTrend($reference),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    private function actions(CarbonInterface $date): array
    {
        return [
            [
                'key' => 'unpaid_households',
                'label' => 'Rumah belum membayar iuran',
                'description' => $date->translatedFormat('d F Y'),
                'count' => $this->kasReport->unpaidHouseholds($date)->count(),
                'tone' => 'danger',
                'route' => 'kas.index',
                'query' => ['date' => $date->toDateString()],
            ],
            [
                'key' => 'open_reports',
                'label' => 'Laporan warga perlu ditangani',
                'description' => 'Status baru atau sedang diproses',
                'count' => Report::query()->open()->count(),
                'tone' => 'warning',
                'route' => 'reports.index',
                'query' => ['filter' => 'open'],
            ],
            [
                'key' => 'pending_letters',
                'label' => 'Permohonan surat perlu diproses',
                'description' => 'Diajukan atau sudah disetujui',
                'count' => LetterRequest::query()->pending()->count(),
                'tone' => 'warning',
                'route' => 'letters.index',
                'query' => ['filter' => 'pending'],
            ],
            [
                'key' => 'missing_checkins',
                'label' => 'Petugas ronda belum check-in',
                'description' => $date->translatedFormat('d F Y'),
                'count' => $this->kasReport->missingCheckins($date)->count(),
                'tone' => 'info',
                'route' => 'ronda.index',
                'query' => [],
            ],
        ];
    }

    private function cashTrend(CarbonInterface $date): Collection
    {
        $from = $date->copy()->subDays(29);
        $totals = CashTransaction::query()
            ->active()
            ->whereBetween('date', [$from->toDateString(), $date->toDateString()])
            ->selectRaw('date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        return collect(range(0, 29))->map(function (int $offset) use ($from, $totals) {
            $day = $from->copy()->addDays($offset);

            return [
                'date' => $day->toDateString(),
                'label' => $day->format('d M'),
                'total' => (int) ($totals[$day->toDateString()] ?? 0),
            ];
        });
    }

    private function recentActivity(): Collection
    {
        return AuditLog::query()
            ->with('actor')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'label' => $this->activityLabel($log->action),
                'actor' => $log->actor?->name ?? 'Sistem',
                'time' => $log->created_at,
            ]);
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'household.created' => 'Rumah/KK ditambahkan',
            'resident.created' => 'Warga ditambahkan',
            'kas.iuran.created' => 'Iuran dicatat',
            'kas.denda.created' => 'Denda dicatat',
            'kas.transaction.cancelled' => 'Transaksi kas dibatalkan',
            'ronda.schedule.created' => 'Jadwal ronda dibuat',
            'ronda.assignment.added' => 'Petugas ronda ditambahkan',
            'report.status_changed' => 'Status laporan diperbarui',
            'letter.status_changed' => 'Status surat diperbarui',
            'announcement.published' => 'Pengumuman diterbitkan',
            default => str($action)->replace('.', ' ')->headline()->toString(),
        };
    }
}
```

- [ ] **Step 4: Run the focused service test**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/AdminDashboardSummaryTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the service contract**

```bash
git add app/Services/AdminDashboardSummary.php tests/Feature/Dashboard/AdminDashboardSummaryTest.php
git commit -m "feat: add admin dashboard operational summary"
```

### Task 2: Add Reusable Admin UI Primitives

**Files:**

- Create: `resources/views/components/admin/page-header.blade.php`
- Create: `resources/views/components/admin/button.blade.php`
- Create: `resources/views/components/admin/panel.blade.php`
- Create: `resources/views/components/admin/metric.blade.php`
- Create: `resources/views/components/admin/status-badge.blade.php`
- Create: `resources/views/components/admin/empty-state.blade.php`

- [ ] **Step 1: Create the page header**

```blade
@props(['title', 'description' => null])

<header {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="display-lg text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-mute">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
```

- [ ] **Step 2: Create the shared button**

```blade
@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-hairline bg-white text-ink hover:bg-canvas-soft',
        'ghost' => 'border border-transparent bg-transparent text-ink-mute hover:bg-canvas-soft hover:text-ink',
        'danger' => 'border border-ruby/30 bg-ruby/10 text-ruby hover:bg-ruby/15',
        default => 'border border-primary bg-primary text-white shadow-level1 hover:bg-primary-deep active:bg-primary-press',
    };

    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-pill px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</button>
@endif
```

- [ ] **Step 3: Create panel, metric, status, and empty-state components**

```blade
{{-- resources/views/components/admin/panel.blade.php --}}
@props(['padding' => true])
<section {{ $attributes->class([
    'rounded-lg border border-hairline bg-white shadow-level1',
    'p-5 sm:p-6' => $padding,
]) }}>{{ $slot }}</section>
```

```blade
{{-- resources/views/components/admin/metric.blade.php --}}
@props(['label', 'value', 'description' => null, 'href' => null])
@php($classes = 'block rounded-lg border border-hairline bg-white p-5 shadow-level1 transition hover:border-primary/30')
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        <p class="text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">{{ $label }}</p>
        <p class="tnum mt-3 text-3xl font-light tracking-tight text-ink">{{ $value }}</p>
        @if ($description)
            <p class="mt-2 text-xs leading-5 text-ink-mute">{{ $description }}</p>
        @endif
    </a>
@else
    <div {{ $attributes->class([$classes]) }}>
        <p class="text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">{{ $label }}</p>
        <p class="tnum mt-3 text-3xl font-light tracking-tight text-ink">{{ $value }}</p>
        @if ($description)
            <p class="mt-2 text-xs leading-5 text-ink-mute">{{ $description }}</p>
        @endif
    </div>
@endif
```

```blade
{{-- resources/views/components/admin/status-badge.blade.php --}}
@props(['tone' => 'neutral'])
@php
    $classes = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        default => 'border-hairline bg-canvas-soft text-ink-mute',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-pill border px-2.5 py-1 text-xs font-medium', $classes]) }}>
    {{ $slot }}
</span>
```

```blade
{{-- resources/views/components/admin/empty-state.blade.php --}}
@props(['title', 'description'])
<div {{ $attributes->class(['px-5 py-10 text-center']) }}>
    <h3 class="text-base font-medium text-ink">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-mute">{{ $description }}</p>
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
```

- [ ] **Step 4: Verify Blade components compile through an existing route**

Run:

```bash
ddev artisan view:clear
ddev artisan test tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: PASS. The components are not used yet, but Blade discovery and the
existing dashboard remain valid.

- [ ] **Step 5: Commit the primitives**

```bash
git add resources/views/components/admin
git commit -m "feat: add reusable admin interface components"
```

### Task 3: Define The Responsive Admin Shell Contract

**Files:**

- Create: `tests/Feature/Dashboard/AdminShellTest.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Modify: `resources/views/livewire/auth/logout-button.blade.php`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Write the failing shell test**

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('renders grouped desktop navigation and the five-item mobile contract', function () {
    $user = User::factory()->create([
        'name' => 'Ketua RT',
        'role' => UserRole::ADMIN_RT,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ringkasan')
        ->assertSee('Data Warga')
        ->assertSee('Operasional')
        ->assertSee('Layanan')
        ->assertSee('Beranda')
        ->assertSee('Lainnya')
        ->assertSee('Ketua RT')
        ->assertSee('Admin RT');
});

it('marks the current admin destination semantically', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get(route('households.index'))
        ->assertOk()
        ->assertSee('aria-current="page"', escape: false);
});
```

- [ ] **Step 2: Run the shell test and verify it fails**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/AdminShellTest.php
```

Expected: FAIL because the current layout has no grouped sidebar or `Lainnya`
drawer.

- [ ] **Step 3: Replace the layout with the responsive shell**

Implement `resources/views/components/layouts/app.blade.php` with these exact
structural rules:

```blade
@props(['title' => 'Smart RT'])

@php
    $groups = [
        'Ringkasan' => [
            ['label' => 'Dashboard', 'mobile' => 'Beranda', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
        ],
        'Data Warga' => [
            ['label' => 'Rumah / KK', 'mobile' => 'Rumah', 'route' => 'households.index', 'active' => request()->routeIs('households.*')],
            ['label' => 'Warga', 'mobile' => 'Warga', 'route' => 'residents.index', 'active' => request()->routeIs('residents.*')],
        ],
        'Operasional' => [
            ['label' => 'Ronda', 'route' => 'ronda.index', 'active' => request()->routeIs('ronda.*')],
            ['label' => 'Sesi Scan', 'route' => 'scan-sessions.index', 'active' => request()->routeIs('scan-sessions.*')],
            ['label' => 'Denda', 'route' => 'denda.index', 'active' => request()->routeIs('denda.*')],
            ['label' => 'Kas', 'mobile' => 'Kas', 'route' => 'kas.index', 'active' => request()->routeIs('kas.*')],
        ],
        'Layanan' => [
            ['label' => 'Pengumuman', 'route' => 'announcements.index', 'active' => request()->routeIs('announcements.*')],
            ['label' => 'Laporan', 'route' => 'reports.index', 'active' => request()->routeIs('reports.*')],
            ['label' => 'Surat', 'route' => 'letters.index', 'active' => request()->routeIs('letters.*')],
            ['label' => 'Voting', 'route' => 'votes.index', 'active' => request()->routeIs('votes.*')],
            ['label' => 'Inventaris', 'route' => 'inventory.index', 'active' => request()->routeIs('inventory.*')],
        ],
    ];

    $mobileRoutes = ['dashboard', 'households.index', 'residents.index', 'kas.index'];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#533afd">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas-soft text-ink antialiased">
    <a href="#main-content" class="sr-only z-50 rounded-sm bg-primary px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <div x-data="{ moreOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="hidden border-r border-hairline bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col" aria-label="Navigasi dashboard">
            <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-3 border-b border-hairline px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">RT</span>
                <span>
                    <span class="block text-sm font-semibold text-ink">Smart RT</span>
                    <span class="block text-xs text-ink-mute">Dashboard Pengurus</span>
                </span>
            </a>

            <nav class="flex-1 overflow-y-auto px-3 py-5">
                @foreach ($groups as $group => $items)
                    <div class="mb-5">
                        <p class="px-3 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-mute">{{ $group }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($items as $item)
                                <a href="{{ route($item['route']) }}"
                                   @if($item['active']) aria-current="page" @endif
                                   class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium transition {{ $item['active'] ? 'bg-primary text-white' : 'text-ink-secondary hover:bg-canvas-soft hover:text-ink' }}">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-hairline p-4">
                <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-xs text-ink-mute">{{ auth()->user()->role->label() }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <x-admin.button variant="secondary" href="{{ route('portal.home') }}" target="_blank" rel="noopener">Portal</x-admin.button>
                    <livewire:auth.logout-button />
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-hairline bg-white/95 px-4 backdrop-blur lg:px-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 lg:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">RT</span>
                    <span class="text-sm font-semibold text-ink">Smart RT</span>
                </a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('portal.home') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-pill px-3 text-sm font-medium text-ink-mute hover:bg-canvas-soft hover:text-ink">
                        Portal Warga
                    </a>
                    <div class="hidden lg:block">{{ auth()->user()->name }}</div>
                </div>
            </header>

            <main id="main-content" class="mx-auto max-w-[90rem] px-4 py-6 pb-28 sm:px-6 lg:px-8 lg:py-8 lg:pb-8">
                {{ $slot }}
            </main>
        </div>

        <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-hairline bg-white/95 px-2 pt-2 shadow-level2 backdrop-blur lg:hidden" aria-label="Navigasi utama">
            @foreach ($groups as $items)
                @foreach ($items as $item)
                    @if (in_array($item['route'], $mobileRoutes, true))
                        <a href="{{ route($item['route']) }}"
                           @if($item['active']) aria-current="page" @endif
                           class="flex min-h-11 items-center justify-center rounded-md px-1 text-center text-xs font-medium {{ $item['active'] ? 'text-primary' : 'text-ink-mute' }}">
                            {{ $item['mobile'] }}
                        </a>
                    @endif
                @endforeach
            @endforeach
            <button type="button" @click="moreOpen = true" class="min-h-11 rounded-md px-1 text-xs font-medium text-ink-mute" aria-controls="mobile-more-menu">
                Lainnya
            </button>
        </nav>

        <div x-cloak x-show="moreOpen" @keydown.escape.window="moreOpen = false" class="fixed inset-0 z-50 lg:hidden">
            <button type="button" @click="moreOpen = false" class="absolute inset-0 bg-ink/40" aria-label="Tutup menu"></button>
            <section id="mobile-more-menu" role="dialog" aria-modal="true" aria-labelledby="mobile-more-title"
                     class="safe-bottom absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-xl bg-white p-5 shadow-level2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="mobile-more-title" class="text-lg font-medium text-ink">Menu lainnya</h2>
                        <p class="text-sm text-ink-mute">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</p>
                    </div>
                    <button type="button" @click="moreOpen = false" class="min-h-11 min-w-11 rounded-full border border-hairline text-ink" aria-label="Tutup menu">×</button>
                </div>
                <div class="mt-5 space-y-5">
                    @foreach ($groups as $group => $items)
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.1em] text-ink-mute">{{ $group }}</p>
                            <div class="mt-2 grid gap-2">
                                @foreach ($items as $item)
                                    @unless(in_array($item['route'], $mobileRoutes, true))
                                        <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-md border border-hairline px-3 text-sm font-medium text-ink-secondary">
                                            {{ $item['label'] }}
                                        </a>
                                    @endunless
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex gap-2 border-t border-hairline pt-4">
                    <x-admin.button variant="secondary" href="{{ route('portal.home') }}" target="_blank" rel="noopener">Portal Warga</x-admin.button>
                    <livewire:auth.logout-button />
                </div>
            </section>
        </div>
    </div>
    @livewireScripts
</body>
</html>
```

- [ ] **Step 4: Update logout styling and loading behavior**

Replace only the button markup in
`resources/views/livewire/auth/logout-button.blade.php`:

```blade
<x-admin.button
    variant="ghost"
    wire:click="logout"
    wire:loading.attr="disabled"
    wire:target="logout"
>
    <span wire:loading.remove wire:target="logout">Keluar</span>
    <span wire:loading wire:target="logout">Keluar...</span>
</x-admin.button>
```

- [ ] **Step 5: Add shell accessibility utilities**

Append to `resources/css/app.css`:

```css
[x-cloak] {
    display: none !important;
}

.safe-bottom {
    padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
}

:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
```

- [ ] **Step 6: Run shell and access tests**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/AdminShellTest.php tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit the responsive shell**

```bash
git add resources/views/components/layouts/app.blade.php resources/views/livewire/auth/logout-button.blade.php resources/css/app.css tests/Feature/Dashboard/AdminShellTest.php
git commit -m "feat: add responsive admin command center shell"
```

### Task 4: Replace The Promotional Dashboard With Operational Content

**Files:**

- Modify: `resources/views/livewire/dashboard/index.blade.php`
- Modify: `tests/Feature/Dashboard/DashboardAccessTest.php`

- [ ] **Step 1: Add the required imports and failing dashboard content assertions**

Add these imports beside the existing `use` declarations at the top of
`tests/Feature/Dashboard/DashboardAccessTest.php`:

```php
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use Illuminate\Support\Carbon;
```

Then append this test:

```php
it('shows operational metrics and action queue instead of a marketing hero', function () {
    Carbon::setTestNow('2026-06-11 09:00:00');
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    Household::factory()->count(2)->create(['is_active' => true]);
    Report::factory()->create();
    LetterRequest::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Yang perlu ditangani')
        ->assertSee('Arus kas 30 hari')
        ->assertSee('Aktivitas terbaru')
        ->assertSee('Rumah belum membayar iuran')
        ->assertDontSee('Sistem Smart RT Aktif');

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run the dashboard access test**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: FAIL because the current dashboard still renders the promotional
hero.

- [ ] **Step 3: Rebuild the dashboard around the summary service**

Replace `resources/views/livewire/dashboard/index.blade.php` with:

```blade
<?php

use App\Services\AdminDashboardSummary;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.app');
title('Dashboard Pengurus');

$summary = computed(fn () => app(AdminDashboardSummary::class)->forDate(today()));
$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-7">
    <x-admin.page-header
        :title="'Selamat datang, '.auth()->user()->name"
        :description="now()->translatedFormat('l, d F Y').' · Ringkasan operasional Smart RT'"
    >
        <x-slot:actions>
            <x-admin.button href="{{ route('scan-sessions.index') }}">Buka sesi scan</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <section aria-label="Ringkasan utama" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.metric label="Rumah aktif" :value="$this->summary['metrics']['households']" description="Rumah yang digunakan dalam operasional RT" :href="route('households.index')" />
        <x-admin.metric label="Warga aktif" :value="$this->summary['metrics']['residents']" description="Warga aktif dengan identitas terdaftar" :href="route('residents.index')" />
        <x-admin.metric label="Kas bulan ini" :value="$this->rupiah($this->summary['metrics']['month_cash'])" description="Total transaksi aktif bulan berjalan" :href="route('kas.index')" />
        <x-admin.metric label="Perlu tindakan" :value="$this->summary['metrics']['action_count']" description="Gabungan pekerjaan operasional terbuka" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
        <x-admin.panel :padding="false" aria-labelledby="action-queue-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="action-queue-title" class="text-lg font-medium text-ink">Yang perlu ditangani</h2>
                <p class="mt-1 text-sm text-ink-mute">Prioritas berdasarkan data operasional saat ini.</p>
            </div>
            <div class="divide-y divide-hairline">
                @foreach ($this->summary['actions'] as $action)
                    <a href="{{ route($action['route'], $action['query']) }}" class="flex min-h-20 items-center gap-4 px-5 py-4 transition hover:bg-canvas-soft sm:px-6">
                        <x-admin.status-badge :tone="$action['tone']">{{ $action['count'] }}</x-admin.status-badge>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-ink">{{ $action['label'] }}</span>
                            <span class="mt-1 block text-xs text-ink-mute">{{ $action['description'] }}</span>
                        </span>
                        <span aria-hidden="true" class="text-ink-mute">→</span>
                    </a>
                @endforeach
            </div>
        </x-admin.panel>

        <x-admin.panel aria-labelledby="cash-trend-title">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="cash-trend-title" class="text-lg font-medium text-ink">Arus kas 30 hari</h2>
                    <p class="mt-1 text-sm text-ink-mute">Transaksi aktif per hari.</p>
                </div>
                <x-admin.button variant="ghost" href="{{ route('kas.transactions') }}">Detail</x-admin.button>
            </div>

            @php($maxCash = max(1, collect($this->summary['cash_trend'])->max('total')))
            <div class="mt-6 flex h-44 items-end gap-1" aria-label="Grafik arus kas 30 hari">
                @foreach ($this->summary['cash_trend'] as $day)
                    <div class="group relative flex min-w-0 flex-1 items-end">
                        <div
                            class="w-full rounded-t-xs bg-primary/25 transition group-hover:bg-primary"
                            style="height: {{ max(3, round(($day['total'] / $maxCash) * 100)) }}%"
                            title="{{ $day['label'] }}: {{ $this->rupiah($day['total']) }}"
                        ></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex justify-between text-xs text-ink-mute">
                <span>{{ collect($this->summary['cash_trend'])->first()['label'] }}</span>
                <span>{{ collect($this->summary['cash_trend'])->last()['label'] }}</span>
            </div>
        </x-admin.panel>
    </div>

    <x-admin.panel :padding="false" aria-labelledby="recent-activity-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="recent-activity-title" class="text-lg font-medium text-ink">Aktivitas terbaru</h2>
            <p class="mt-1 text-sm text-ink-mute">Perubahan penting yang tercatat dalam audit log.</p>
        </div>

        @forelse ($this->summary['recent_activity'] as $activity)
            <div class="grid gap-1 border-b border-hairline px-5 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_12rem_9rem] sm:px-6">
                <p class="text-sm font-medium text-ink">{{ $activity['label'] }}</p>
                <p class="text-sm text-ink-mute">{{ $activity['actor'] }}</p>
                <time class="tnum text-xs text-ink-mute sm:text-right" datetime="{{ $activity['time']->toIso8601String() }}">
                    {{ $activity['time']->diffForHumans() }}
                </time>
            </div>
        @empty
            <x-admin.empty-state title="Belum ada aktivitas" description="Perubahan administratif akan muncul di sini setelah dicatat." />
        @endforelse
    </x-admin.panel>
</div>
```

- [ ] **Step 4: Run dashboard tests**

Run:

```bash
ddev artisan test tests/Feature/Dashboard/AdminDashboardSummaryTest.php tests/Feature/Dashboard/AdminShellTest.php tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the operational dashboard**

```bash
git add resources/views/livewire/dashboard/index.blade.php tests/Feature/Dashboard/DashboardAccessTest.php
git commit -m "feat: redesign dashboard as operational command center"
```

### Task 5: Verify Build And Full Regression Suite

**Files:** No source changes unless verification reveals a defect.

- [ ] **Step 1: Format PHP**

Run:

```bash
ddev exec vendor/bin/pint --dirty
```

Expected: modified PHP files are formatted without errors.

- [ ] **Step 2: Run focused tests after formatting**

Run:

```bash
ddev artisan test tests/Feature/Dashboard
```

Expected: PASS.

- [ ] **Step 3: Run the complete Pest suite**

Run:

```bash
ddev artisan test
```

Expected: PASS with no regressions.

- [ ] **Step 4: Build production assets**

Run:

```bash
ddev npm run build
```

Expected: Vite completes successfully with no Tailwind or JavaScript errors.

- [ ] **Step 5: Commit formatting fixes if Pint changed tracked files**

```bash
git add app/Services/AdminDashboardSummary.php tests/Feature/Dashboard
git commit -m "style: format admin command center phase one"
```

Skip this commit when `git diff --check` and `git status --short` show no
Phase 1 formatting changes.

### Task 6: Run Playwright Responsive Acceptance

**Files:** No committed screenshots or temporary scripts.

- [ ] **Step 1: Confirm the DDEV URL**

Run:

```bash
ddev describe
```

Expected: `http://smart-rt.ddev.site` is available.

- [ ] **Step 2: Verify desktop login and dashboard**

Use Playwright at `1440x1000`:

1. Open `http://smart-rt.ddev.site/login`.
2. Sign in with `admin@smartrt.test` / `password`.
3. Confirm the title is `Dashboard Pengurus - Smart RT`.
4. Confirm the sidebar shows all four navigation groups.
5. Confirm the first viewport shows metrics and `Yang perlu ditangani`.
6. Confirm there is no horizontal page overflow.
7. Confirm there are no console errors or framework overlays.

Expected: all checks pass.

- [ ] **Step 3: Verify the phone dashboard and navigation**

Resize Playwright to `390x844`:

1. Confirm the bottom bar shows exactly `Beranda`, `Rumah`, `Warga`, `Kas`,
   and `Lainnya`.
2. Measure each bottom-bar control and confirm height is at least `44px`.
3. Open `Lainnya`.
4. Confirm all remaining modules, account identity, portal link, and logout
   are available.
5. Press Escape and confirm the drawer closes.
6. Open `Lainnya` again, select `Laporan`, and confirm navigation reaches
   `/dashboard/laporan`.
7. Return to the dashboard and confirm the fixed navigation does not obscure
   the last activity row.

Expected: all checks pass with no page-level horizontal overflow.

- [ ] **Step 4: Capture temporary evidence outside the repository**

Save screenshots to:

```text
/tmp/smart-rt-admin-dashboard-desktop.png
/tmp/smart-rt-admin-dashboard-mobile.png
/tmp/smart-rt-admin-more-drawer-mobile.png
```

Expected: screenshots show the light admin shell, operational first viewport,
and complete mobile navigation.

- [ ] **Step 5: Record the fidelity ledger in the execution report**

Compare the implementation against:

- `DESIGN.md`
- `docs/superpowers/specs/2026-06-10-admin-operational-command-center-design.md`

Record at least these checks:

1. Indigo is limited to primary actions, selection, focus, and links.
2. Admin surfaces use white over `#f6f9fc`.
3. Numeric and currency values use tabular figures.
4. The action queue is visually stronger than analytics.
5. Desktop uses grouped sidebar navigation.
6. Mobile uses five fixed destinations plus `Lainnya`.
7. Mobile controls meet the 44px target.
8. No dark promotional hero remains.

Expected: no material mismatch remains.

### Task 7: Phase 1 Completion Commit

**Files:** All Phase 1 files only.

- [ ] **Step 1: Inspect the final diff without touching unrelated work**

Run:

```bash
git status --short
git diff --check
git diff -- app/Services/AdminDashboardSummary.php resources/css/app.css resources/views/components/admin resources/views/components/layouts/app.blade.php resources/views/livewire/auth/logout-button.blade.php resources/views/livewire/dashboard/index.blade.php tests/Feature/Dashboard
```

Expected:

- No whitespace errors.
- No portal file is included.
- Existing unrelated user changes remain untouched.
- No Playwright screenshot is stored in the repository.

- [ ] **Step 2: Create the final phase commit only when uncommitted Phase 1 files remain**

```bash
git add app/Services/AdminDashboardSummary.php resources/css/app.css resources/views/components/admin resources/views/components/layouts/app.blade.php resources/views/livewire/auth/logout-button.blade.php resources/views/livewire/dashboard/index.blade.php tests/Feature/Dashboard
git commit -m "feat: complete admin command center foundation"
```

Expected: commit succeeds, or the step is skipped because every Phase 1 change
was already committed task-by-task.

## Follow-On Plans

After Phase 1 passes acceptance, write and execute separate plans in this
order:

1. **Phase 2A - Core data:** Rumah/KK and Warga.
2. **Phase 2B - Finance operations:** Kas, transactions, Sesi Scan, and Denda.
3. **Phase 2C - Ronda:** schedule and detail workflows.
4. **Phase 3 - Services and assets:** Pengumuman, Laporan, Surat, Voting,
   Inventaris, and household QR.

Each follow-on plan must reuse the Phase 1 admin components, preserve current
business behavior, add route-specific tests, and repeat desktop/mobile
Playwright acceptance.
