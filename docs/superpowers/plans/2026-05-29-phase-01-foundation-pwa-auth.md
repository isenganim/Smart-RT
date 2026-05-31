# Phase 01 Foundation PWA and Auth Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Bootstrap Smart RT as a Laravel 12 PWA with Livewire Volt, Alpine.js, Tailwind CSS, MySQL, authenticated pengurus dashboard, roles, and audit logging foundation.

**Architecture:** Use a single Laravel application with server-rendered Livewire/Volt pages. Public warga pages will be added in later phases; this phase creates admin-only dashboard infrastructure, auth, role middleware, layout, test setup, and deployment-ready environment defaults.

**Tech Stack:** Laravel 12, PHP 8.3, MySQL 8, Livewire 3, Volt, Alpine.js, Tailwind CSS, Pest, Vite, Laravel PWA manifest/service worker, spatie/laravel-permission optional via native enum roles for MVP.

---

## File Structure

- Create: `.ddev/` via `ddev config` for local Docker-based development.
- Create: `composer.json` via `ddev composer create-project`.
- Create: `.env.example` with MySQL, app, queue, session, and mail defaults.
- Create: `app/Enums/UserRole.php` for `admin_rt` and `bendahara` roles.
- Create: `database/migrations/*_add_role_to_users_table.php`.
- Create: `database/migrations/*_create_audit_logs_table.php`.
- Create: `app/Models/AuditLog.php`.
- Create: `app/Http/Middleware/EnsurePengurus.php`.
- Modify: `bootstrap/app.php` to register middleware alias.
- Create: `app/Support/Audit.php` helper service.
- Create: `routes/web.php` with public home redirect, dashboard route, and auth routes.
- Create: `resources/views/components/layouts/app.blade.php`.
- Create: `resources/views/components/layouts/auth.blade.php`.
- Create: `resources/views/livewire/dashboard/index.blade.php`.
- Create: `resources/views/livewire/auth/login.blade.php`.
- Create: `resources/views/livewire/auth/logout-button.blade.php`.
- Create: `public/manifest.webmanifest`.
- Create: `public/sw.js`.
- Create: `tests/Feature/Auth/LoginTest.php`.
- Create: `tests/Feature/Dashboard/DashboardAccessTest.php`.
- Create: `tests/Feature/Audit/AuditLogTest.php`.

## Task 1: Create Laravel Application Skeleton

**Files:**
- Create: project root Laravel files
- Modify: `.env.example`

- [x] **Step 1: Configure DDEV and create the Laravel project in `smart-rt`**

Run from `/home/ageng/Projects/Programming/monorepo/smart-rt`:

```bash
ddev config --project-type=laravel --docroot=public --create-docroot
ddev start
ddev composer create-project laravel/laravel temp-laravel "^12.0"
rsync -a temp-laravel/ ./
rm -rf temp-laravel
```

Expected: `.ddev/`, `artisan`, `composer.json`, `app/`, `routes/`, `database/`, and `resources/` exist. Commit `.ddev/` with the project; it is the shared local development environment.

- [x] **Step 2: Install Livewire Volt and Pest**

```bash
ddev composer require livewire/livewire livewire/volt
ddev composer remove phpunit/phpunit --dev
ddev composer require pestphp/pest --dev --with-all-dependencies
ddev composer require pestphp/pest-plugin-laravel --dev
ddev artisan volt:install
ddev exec ./vendor/bin/pest --init
```

Expected: Composer installs packages and creates Pest configuration.

- [x] **Step 3: Install frontend dependencies**

```bash
ddev npm install
ddev npm install -D tailwindcss @tailwindcss/vite alpinejs
```

Expected: `package-lock.json` and `node_modules/` are created.

- [x] **Step 4: Update `.env.example`**

Set these values:

```dotenv
APP_NAME="Smart RT"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://smart-rt.ddev.site

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=db
DB_USERNAME=db
DB_PASSWORD=db

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

- [x] **Step 5: Verify framework boot**

```bash
cp .env.example .env
ddev artisan key:generate
ddev artisan test
```

Expected: default tests pass.

- [x] **Step 6: Commit**

```bash
git add .
git commit -m "chore: bootstrap Laravel Smart RT application"
```

## Task 2: Add User Roles

**Files:**
- Create: `app/Enums/UserRole.php`
- Create: `database/migrations/*_add_role_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Auth/UserRoleTest.php`

- [x] **Step 1: Write failing role tests**

Create `tests/Feature/Auth/UserRoleTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('casts user role to enum', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    expect($user->fresh()->role)->toBe(UserRole::ADMIN_RT);
});

it('detects pengurus users', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $bendahara = User::factory()->create(['role' => UserRole::BENDAHARA]);

    expect($admin->isPengurus())->toBeTrue();
    expect($bendahara->isPengurus())->toBeTrue();
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Auth/UserRoleTest.php
```

Expected: FAIL because `App\Enums\UserRole` does not exist.

- [x] **Step 3: Create role enum**

Create `app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN_RT = 'admin_rt';
    case BENDAHARA = 'bendahara';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_RT => 'Admin RT',
            self::BENDAHARA => 'Bendahara',
        };
    }
}
```

- [x] **Step 4: Add migration**

```bash
ddev artisan make:migration add_role_to_users_table --table=users
```

Migration body:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('admin_rt')->after('password');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

- [x] **Step 5: Update user model**

In `app/Models/User.php`, add:

```php
use App\Enums\UserRole;
```

Update casts:

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];
}

public function isPengurus(): bool
{
    return in_array($this->role, [UserRole::ADMIN_RT, UserRole::BENDAHARA], true);
}
```

- [x] **Step 6: Run tests**

```bash
ddev artisan migrate:fresh --env=testing
ddev artisan test tests/Feature/Auth/UserRoleTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
git add app/Enums/UserRole.php app/Models/User.php database/migrations tests/Feature/Auth/UserRoleTest.php
git commit -m "feat: add pengurus user roles"
```

## Task 3: Build Login and Dashboard Access

**Files:**
- Create: `app/Http/Middleware/EnsurePengurus.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/views/livewire/auth/login.blade.php`
- Create: `resources/views/livewire/dashboard/index.blade.php`
- Test: `tests/Feature/Dashboard/DashboardAccessTest.php`

- [x] **Step 1: Write failing access tests**

Create `tests/Feature/Dashboard/DashboardAccessTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('redirects guests from dashboard to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

it('allows admin rt to open dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::ADMIN_RT]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Pengurus');
});

it('allows bendahara to open dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::BENDAHARA]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Pengurus');
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: FAIL because routes/views are missing.

- [x] **Step 3: Create pengurus middleware**

Create `app/Http/Middleware/EnsurePengurus.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePengurus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPengurus()) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [x] **Step 4: Register middleware alias**

In `bootstrap/app.php`:

```php
use App\Http\Middleware\EnsurePengurus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'pengurus' => EnsurePengurus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

- [x] **Step 5: Create routes**

Replace `routes/web.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/dashboard');

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
});
```

- [x] **Step 6: Create dashboard view**

Create `resources/views/livewire/dashboard/index.blade.php`:

```blade
<x-layouts.app title="Dashboard Pengurus">
    <div class="space-y-4">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard Pengurus</h1>
        <p class="text-slate-600">Selamat datang di Smart RT.</p>
    </div>
</x-layouts.app>
```

- [x] **Step 7: Create login Volt page**

Create `resources/views/livewire/auth/login.blade.php`:

```blade
<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{state, rules};

state(['email' => '', 'password' => '', 'remember' => false]);

rules([
    'email' => ['required', 'email'],
    'password' => ['required', 'string'],
]);

$login = function () {
    $this->validate();

    if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        throw ValidationException::withMessages([
            'email' => 'Email atau password tidak sesuai.',
        ]);
    }

    request()->session()->regenerate();

    return $this->redirectRoute('dashboard', navigate: true);
};

?>

<x-layouts.auth title="Login Pengurus">
    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input wire:model="email" type="email" class="mt-1 w-full rounded-lg border-slate-300" autofocus>
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input wire:model="password" type="password" class="mt-1 w-full rounded-lg border-slate-300">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-300">
            Ingat saya
        </label>

        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
            Masuk
        </button>
    </form>
</x-layouts.auth>
```

- [x] **Step 8: Run tests**

```bash
ddev artisan test tests/Feature/Dashboard/DashboardAccessTest.php
```

Expected: PASS.

- [x] **Step 9: Commit**

```bash
git add app/Http/Middleware/EnsurePengurus.php bootstrap/app.php routes/web.php resources/views/livewire tests/Feature/Dashboard
git commit -m "feat: add pengurus login and dashboard access"
```

## Task 4: Add Layouts and PWA Assets

**Files:**
- Create: `resources/views/components/layouts/app.blade.php`
- Create: `resources/views/components/layouts/auth.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Create: `public/manifest.webmanifest`
- Create: `public/sw.js`
- Test: `tests/Feature/Pwa/PwaAssetsTest.php`

- [x] **Step 1: Write failing PWA tests**

Create `tests/Feature/Pwa/PwaAssetsTest.php`:

```php
<?php

it('serves web manifest', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Smart RT');
});

it('serves service worker', function () {
    $this->get('/sw.js')
        ->assertOk()
        ->assertSee('smart-rt-cache');
});
```

- [x] **Step 2: Run failing tests**

```bash
ddev artisan test tests/Feature/Pwa/PwaAssetsTest.php
```

Expected: FAIL because files are missing or content does not match.

- [x] **Step 3: Create app layout**

Create `resources/views/components/layouts/app.blade.php`:

```blade
@props(['title' => 'Smart RT'])

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
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        <header class="border-b bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <a href="{{ route('dashboard') }}" class="font-semibold text-emerald-700">Smart RT</a>
                <livewire:auth.logout-button />
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
```

- [x] **Step 4: Create auth layout**

Create `resources/views/components/layouts/auth.blade.php`:

```blade
@props(['title' => 'Login'])

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
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-semibold text-emerald-700">Smart RT</h1>
                <p class="text-sm text-slate-500">{{ $title }}</p>
            </div>
            {{ $slot }}
        </section>
    </main>
    @livewireScripts
</body>
</html>
```

- [x] **Step 5: Create logout button**

Create `resources/views/livewire/auth/logout-button.blade.php`:

```blade
<?php

use Illuminate\Support\Facades\Auth;

$logout = function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return $this->redirectRoute('login', navigate: true);
};

?>

<button wire:click="logout" class="text-sm font-medium text-slate-600 hover:text-slate-900">
    Keluar
</button>
```

- [x] **Step 6: Create PWA manifest**

Create `public/manifest.webmanifest`:

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

- [x] **Step 7: Create service worker**

Create `public/sw.js`:

```js
const CACHE_NAME = 'smart-rt-cache-v1';
const PRECACHE_URLS = ['/', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)));
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
```

- [x] **Step 8: Register service worker**

In `resources/js/app.js`:

```js
import './bootstrap';

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}
```

- [x] **Step 9: Run tests and build assets**

```bash
ddev artisan test tests/Feature/Pwa/PwaAssetsTest.php tests/Feature/Dashboard/DashboardAccessTest.php
ddev npm run build
```

Expected: tests PASS and Vite build succeeds.

- [x] **Step 10: Commit**

```bash
git add resources public tests/Feature/Pwa package.json package-lock.json
git commit -m "feat: add Smart RT layouts and PWA assets"
```

## Task 5: Add Audit Log Foundation

**Files:**
- Create: `database/migrations/*_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Create: `app/Support/Audit.php`
- Test: `tests/Feature/Audit/AuditLogTest.php`

- [x] **Step 1: Write failing audit tests**

Create `tests/Feature/Audit/AuditLogTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;

it('records an audit log with actor and metadata', function () {
    $user = User::factory()->create();

    Audit::record(
        actor: $user,
        action: 'dashboard.opened',
        subjectType: 'dashboard',
        subjectId: null,
        metadata: ['source' => 'test']
    );

    $log = AuditLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($user->id);
    expect($log->action)->toBe('dashboard.opened');
    expect($log->metadata)->toBe(['source' => 'test']);
});
```

- [x] **Step 2: Run failing test**

```bash
ddev artisan test tests/Feature/Audit/AuditLogTest.php
```

Expected: FAIL because audit classes do not exist.

- [x] **Step 3: Create migration**

```bash
ddev artisan make:migration create_audit_logs_table
```

Migration body:

```php
public function up(): void
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('action');
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->json('metadata')->nullable();
        $table->string('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();

        $table->index(['subject_type', 'subject_id']);
        $table->index('action');
    });
}

public function down(): void
{
    Schema::dropIfExists('audit_logs');
}
```

- [x] **Step 4: Create model**

Create `app/Models/AuditLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
```

- [x] **Step 5: Create audit helper**

Create `app/Support/Audit.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class Audit
{
    public static function record(
        ?User $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
```

- [x] **Step 6: Run audit tests**

```bash
ddev artisan migrate:fresh --env=testing
ddev artisan test tests/Feature/Audit/AuditLogTest.php
```

Expected: PASS.

- [x] **Step 7: Commit**

```bash
git add app/Models/AuditLog.php app/Support/Audit.php database/migrations tests/Feature/Audit
git commit -m "feat: add audit log foundation"
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
ddev start
ddev launch /login
```

Open `https://smart-rt.ddev.site/login`, login with a seeded/admin user created manually via `ddev artisan tinker`, and verify `/dashboard` loads.
