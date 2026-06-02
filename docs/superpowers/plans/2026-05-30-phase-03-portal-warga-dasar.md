# Phase 03 Portal Warga Dasar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build the public warga portal foundation: a no-login responsive landing page reachable from a WhatsApp link, and a reusable phone-number verification mechanism that validates a nomor HP against active warga (Phase 02 `Resident`). The portal returns the matched active resident so later phases (laporan, surat, voting, check-in) can gate official actions behind a registered, active phone number.

**Architecture:** Continue the single Laravel 12 application. Add a public route group with **no** `auth`/`pengurus` middleware, served by a dedicated `x-layouts.public` layout that is visually distinct from the pengurus dashboard and optimized for low-IT-literacy warga. Move the application root `/` to the portal home; pengurus continue to use `/dashboard` (login required). Introduce a single reusable `App\Services\ResidentLookup` service that normalizes a raw phone (reusing Phase 02 `App\Support\PhoneNumber`) and resolves an active `Resident`, returning a typed result. The public phone-verification action is rate limited to protect the open endpoint from enumeration/abuse. Public submissions to data-creating features are out of scope here and arrive in later phases; this phase ships the portal shell, the verification service, and a self-service "cek nomor HP" page.

**Tech Stack:** Laravel 12, PHP 8.4, MariaDB 11.8, Livewire 4, Volt, Alpine.js, Tailwind CSS, Pest. Builds on Phase 01 (`x-layouts.app`, routing) and Phase 02 (`Resident`, `App\Support\PhoneNumber`).

**Implementation status:** Complete. All task checkboxes below have been marked implemented. Final implementation uses Volt `layout()` / `title()` helpers for routed Livewire pages, and public phone verification confirms registration without exposing the resident name.

---

## Prerequisites

- Phase 01 and Phase 02 are complete: the app boots, `auth` + `pengurus` middleware exist, and `App\Models\Resident` plus `App\Support\PhoneNumber` are available.
- `php artisan migrate:fresh --env=testing` runs cleanly before starting.

## File Structure

- Create: `app/Services/ResidentLookup.php` (phone normalization + active resident resolution).
- Create: `app/Services/PhoneLookupResult.php` (typed result object).
- Modify: `routes/web.php` (move `/` to portal home, add public portal routes).
- Create: `resources/views/components/layouts/public.blade.php` (warga-facing layout).
- Create: `resources/views/livewire/portal/home.blade.php` (public landing page).
- Create: `resources/views/livewire/portal/verify.blade.php` (cek nomor HP page).
- Create: `resources/views/components/portal/phone-field.blade.php` (reusable phone input partial).
- Test: `tests/Feature/Portal/ResidentLookupTest.php`.
- Test: `tests/Feature/Portal/PortalHomeTest.php`.
- Test: `tests/Feature/Portal/PhoneVerificationTest.php`.

## Task 1: Build the Resident Lookup Service

**Files:**

- Create: `app/Services/PhoneLookupResult.php`
- Create: `app/Services/ResidentLookup.php`
- Test: `tests/Feature/Portal/ResidentLookupTest.php`

- [x] **Step 1: Write failing lookup tests**

Create `tests/Feature/Portal/ResidentLookupTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use App\Services\ResidentLookup;

beforeEach(function () {
    $this->lookup = new ResidentLookup();
    $this->household = Household::factory()->create();
});

it('resolves an active resident by raw phone input', function () {
    $resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    $result = $this->lookup->resolve('0812-3456-7890');

    expect($result->found())->toBeTrue();
    expect($result->resident->is($resident))->toBeTrue();
    expect($result->message)->toBeNull();
});

it('reports an unknown phone as not registered', function () {
    $result = $this->lookup->resolve('0899-0000-0000');

    expect($result->found())->toBeFalse();
    expect($result->resident)->toBeNull();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('rejects a phone that belongs only to an inactive resident', function () {
    Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => false,
    ]);

    $result = $this->lookup->resolve('81234567890');

    expect($result->found())->toBeFalse();
    expect($result->message)->toBe('Nomor HP belum terdaftar. Silakan hubungi pengurus RT.');
});

it('treats blank input as not registered without querying', function () {
    $result = $this->lookup->resolve('');

    expect($result->found())->toBeFalse();
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Portal/ResidentLookupTest.php
```

Expected: FAIL because the service classes do not exist.

- [x] **Step 3: Create the typed result object**

Create `app/Services/PhoneLookupResult.php`:

```php
<?php

namespace App\Services;

use App\Models\Resident;

class PhoneLookupResult
{
    public function __construct(
        public readonly ?Resident $resident = null,
        public readonly ?string $message = null,
    ) {}

    public static function hit(Resident $resident): self
    {
        return new self(resident: $resident);
    }

    public static function miss(string $message): self
    {
        return new self(message: $message);
    }

    public function found(): bool
    {
        return $this->resident !== null;
    }
}
```

- [x] **Step 4: Create the lookup service**

Create `app/Services/ResidentLookup.php`:

```php
<?php

namespace App\Services;

use App\Models\Resident;
use App\Support\PhoneNumber;

class ResidentLookup
{
    public const NOT_REGISTERED = 'Nomor HP belum terdaftar. Silakan hubungi pengurus RT.';

    public function resolve(?string $rawPhone): PhoneLookupResult
    {
        $normalized = PhoneNumber::normalize($rawPhone);

        if ($normalized === '') {
            return PhoneLookupResult::miss(self::NOT_REGISTERED);
        }

        $resident = Resident::query()
            ->where('is_active', true)
            ->where('phone', $normalized)
            ->first();

        return $resident
            ? PhoneLookupResult::hit($resident)
            : PhoneLookupResult::miss(self::NOT_REGISTERED);
    }
}
```

- [x] **Step 5: Run tests**

```bash
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Portal/ResidentLookupTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add app/Services/PhoneLookupResult.php app/Services/ResidentLookup.php tests/Feature/Portal/ResidentLookupTest.php
git commit -m "feat: add resident lookup service for warga portal"
```

## Task 2: Build the Public Portal Layout and Landing Page

**Files:**

- Modify: `routes/web.php`
- Create: `resources/views/components/layouts/public.blade.php`
- Create: `resources/views/livewire/portal/home.blade.php`
- Test: `tests/Feature/Portal/PortalHomeTest.php`

- [x] **Step 1: Write failing portal home tests**

Create `tests/Feature/Portal/PortalHomeTest.php`:

```php
<?php

it('serves the warga portal home without authentication', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Portal Warga')
        ->assertSee('Cek Nomor HP');
});

it('does not require login for the portal home', function () {
    $this->get('/')->assertDontSee('Login Pengurus');
});

it('keeps the pengurus dashboard protected', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Portal/PortalHomeTest.php
```

Expected: FAIL because `/` still redirects to `/dashboard` and the portal view is missing.

- [x] **Step 3: Update routes**

In `routes/web.php`, replace the `Route::redirect('/', '/dashboard')` line with public portal routes placed **outside** the `auth`/`pengurus` group:

```php
use Livewire\Volt\Volt;

Volt::route('/', 'portal.home')->name('portal.home');
Volt::route('/cek-nomor', 'portal.verify')->name('portal.verify');

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
    Volt::route('/dashboard/rumah', 'households.index')->name('households.index');
    Volt::route('/dashboard/rumah/{household}/qr', 'households.qr')->name('households.qr');
    Volt::route('/dashboard/warga', 'residents.index')->name('residents.index');
});
```

- [x] **Step 4: Create the public layout**

Create `resources/views/components/layouts/public.blade.php`:

```blade
@props(['title' => 'Portal Warga'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-emerald-50 text-slate-900">
    <div class="mx-auto flex min-h-screen max-w-xl flex-col">
        <header class="flex items-center justify-between px-4 py-4">
            <a href="{{ route('portal.home') }}" class="text-lg font-semibold text-emerald-700">Smart RT</a>
            <span class="text-xs text-slate-500">Portal Warga</span>
        </header>
        <main class="flex-1 px-4 pb-10">
            {{ $slot }}
        </main>
        <footer class="px-4 py-4 text-center text-xs text-slate-400">
            Hubungi pengurus RT jika data Anda belum terdaftar.
        </footer>
    </div>
    @livewireScripts
</body>
</html>
```

- [x] **Step 5: Create the portal home page**

Create `resources/views/livewire/portal/home.blade.php`:

```blade
<?php

use function Livewire\Volt\{state};

state([
    'services' => [
        ['label' => 'Cek Nomor HP', 'route' => 'portal.verify', 'desc' => 'Pastikan nomor HP Anda sudah terdaftar.', 'ready' => true],
        ['label' => 'Pengumuman', 'route' => null, 'desc' => 'Informasi terbaru dari RT.', 'ready' => false],
        ['label' => 'Jadwal Ronda', 'route' => null, 'desc' => 'Lihat jadwal ronda warga.', 'ready' => false],
        ['label' => 'Lapor Warga', 'route' => null, 'desc' => 'Kirim laporan ke pengurus.', 'ready' => false],
    ],
]);

?>

<x-layouts.public title="Portal Warga">
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Selamat datang di Portal Warga</h1>
            <p class="mt-1 text-sm text-slate-600">
                Pilih layanan di bawah ini. Untuk aksi resmi, Anda akan diminta memasukkan nomor HP yang terdaftar di RT.
            </p>
        </div>

        <div class="grid gap-3">
            @foreach ($services as $service)
                @if ($service['ready'])
                    <a href="{{ route($service['route']) }}"
                       class="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">
                        <span>
                            <span class="block font-medium text-slate-900">{{ $service['label'] }}</span>
                            <span class="block text-sm text-slate-500">{{ $service['desc'] }}</span>
                        </span>
                        <span class="text-emerald-600">&rarr;</span>
                    </a>
                @else
                    <div class="flex items-center justify-between rounded-xl bg-white/60 p-4 ring-1 ring-slate-200">
                        <span>
                            <span class="block font-medium text-slate-500">{{ $service['label'] }}</span>
                            <span class="block text-sm text-slate-400">{{ $service['desc'] }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-400">Segera</span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</x-layouts.public>
```

- [x] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Portal/PortalHomeTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
git add routes/web.php resources/views/components/layouts/public.blade.php resources/views/livewire/portal/home.blade.php tests/Feature/Portal/PortalHomeTest.php
git commit -m "feat: add public warga portal layout and landing page"
```

## Task 3: Build the Cek Nomor HP Page with Rate Limiting

**Files:**

- Create: `resources/views/components/portal/phone-field.blade.php`
- Create: `resources/views/livewire/portal/verify.blade.php`
- Test: `tests/Feature/Portal/PhoneVerificationTest.php`

- [x] **Step 1: Write failing verification tests**

Create `tests/Feature/Portal/PhoneVerificationTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-verify:127.0.0.1');
    $this->household = Household::factory()->create();
});

it('confirms a registered active phone', function () {
    Resident::factory()->for($this->household)->create([
        'name' => 'Andi',
        'phone' => '81234567890',
        'is_active' => true,
    ]);

    Volt::test('portal.verify')
        ->set('phone', '0812-3456-7890')
        ->call('check')
        ->assertSet('verified', true)
        ->assertDontSee('Andi')
        ->assertSee('Nomor ini sudah terdaftar di sistem RT.');
});

it('shows a friendly message for an unknown phone', function () {
    Volt::test('portal.verify')
        ->set('phone', '0899-0000-0000')
        ->call('check')
        ->assertSet('verified', false)
        ->assertSee('belum terdaftar');
});

it('blocks excessive verification attempts', function () {
    $component = Volt::test('portal.verify');

    foreach (range(1, 6) as $ignored) {
        $component->set('phone', '0899-0000-0000')->call('check');
    }

    $component->assertSee('Terlalu banyak percobaan');
});
```

- [x] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/Portal/PhoneVerificationTest.php
```

Expected: FAIL because the verify view does not exist.

- [x] **Step 3: Create the reusable phone-field partial**

Create `resources/views/components/portal/phone-field.blade.php`:

```blade
@props(['model' => 'phone', 'label' => 'Nomor HP'])

<div>
    <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input
        wire:model="{{ $model }}"
        type="tel"
        inputmode="numeric"
        placeholder="Contoh: 0812xxxxxxx"
        class="mt-1 w-full rounded-lg border-slate-300 text-base"
    >
    @error($model) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
```

- [x] **Step 4: Create the verify page**

Create `resources/views/livewire/portal/verify.blade.php`:

```blade
<?php

use App\Services\ResidentLookup;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules};

state([
    'phone' => '',
    'verified' => false,
    'feedback' => null,
]);

rules([
    'phone' => ['required', 'string', 'max:30'],
]);

$check = function (ResidentLookup $lookup) {
    $this->validate();

    $key = 'portal-verify:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->verified = false;
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $lookup->resolve($this->phone);

    if ($result->found()) {
        $this->verified = true;
        $this->feedback = null;
    } else {
        $this->verified = false;
        $this->feedback = $result->message;
    }
};

?>

<x-layouts.public title="Cek Nomor HP">
    <div class="space-y-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Cek Nomor HP</h1>
            <p class="mt-1 text-sm text-slate-600">
                Masukkan nomor HP Anda untuk memastikan sudah terdaftar di data warga.
            </p>

            <form wire:submit="check" class="mt-4 space-y-4">
                <x-portal.phone-field model="phone" />
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
                    Cek Sekarang
                </button>
            </form>
        </div>

        @if ($verified)
            <div class="rounded-2xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-200">
                <p class="text-sm text-emerald-700">Nomor HP terdaftar dan aktif.</p>
                <p class="mt-1 text-sm font-medium text-emerald-800">Nomor ini sudah terdaftar di sistem RT.</p>
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

- [x] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Portal/PhoneVerificationTest.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add resources/views/components/portal/phone-field.blade.php resources/views/livewire/portal/verify.blade.php tests/Feature/Portal/PhoneVerificationTest.php
git commit -m "feat: add cek nomor HP portal page with rate limiting"
```

## Task 4: Link the Portal from the PWA and Dashboard

**Files:**

- Modify: `public/manifest.webmanifest`
- Modify: `resources/views/components/layouts/app.blade.php`

- [x] **Step 1: Point the PWA start_url at the portal**

In `public/manifest.webmanifest`, confirm `start_url` is `/` so installing the PWA from a WhatsApp link opens the warga portal:

```json
{
    "name": "Smart RT",
    "short_name": "Smart RT",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#f8fafc",
    "theme_color": "#059669",
    "description": "Administrasi RT, ronda, kas, dan layanan warga.",
    "icons": []
}
```

- [x] **Step 2: Add a portal link in the pengurus header**

In `resources/views/components/layouts/app.blade.php`, add a link so pengurus can preview the warga portal:

```blade
<a href="{{ route('portal.home') }}" class="text-slate-600 hover:text-slate-900" target="_blank" rel="noopener">Portal Warga</a>
```

- [x] **Step 3: Run the full suite**

```bash
php artisan test
```

Expected: PASS.

- [x] **Step 4: Commit**

```bash
git add public/manifest.webmanifest resources/views/components/layouts/app.blade.php
git commit -m "chore: link warga portal from pwa and dashboard"
```

## Final Verification

- [x] Run all checks:

```bash
ddev exec php artisan test
```

Expected: PASS. Last verified after Sprint 2 review fixes: 65 tests, 134 assertions.

- [x] Manual smoke test:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

- Open `http://localhost:8000/` and confirm the warga portal home loads with no login.
- Confirm `/dashboard` still redirects guests to `/login`.
- Open `Cek Nomor HP`, enter a seeded resident's phone (formatted with spaces/dashes), and confirm it reports registered + active without showing the resident name.
- Enter an unregistered number and confirm the "Nomor HP belum terdaftar" message.
- Submit the check more than 5 times quickly and confirm the rate-limit message appears.
