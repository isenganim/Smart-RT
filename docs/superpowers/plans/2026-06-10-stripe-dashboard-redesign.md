# Stripe-inspired Light Canvas Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Smart RT Admin Dashboard from the current dark glass-morphic theme and default/raw layouts to a premium Stripe-inspired Light Canvas theme (white cards with hairline borders over a cool gray-tinted canvas background, utilizing Inter font tracking and tabular numbers).

**Architecture:** Update the global dashboard layout layout blade file and rewrite the template views (updating CSS classes, borders, backgrounds, and applying the `.tnum` class to numeric elements) while ensuring that functional features, components, and livewire bindings remain untouched and the test suite passes.

**Tech Stack:** Laravel 12, Livewire 4, Volt, Tailwind v4, Pest testing.

---

### Task 1: Dashboard Layout Shell Update

**Files:**
*   Modify: `resources/views/components/layouts/app.blade.php`

- [ ] **Step 1: Modify the layout file**
    Modify `resources/views/components/layouts/app.blade.php` to use the Stripe Light Canvas styles.
    Update the body class to:
    ```html
    <body class="min-h-screen bg-[#f6f9fc] text-[#0d253d] antialiased font-sans selection:bg-[#533afd] selection:text-white">
    ```
    Change the header to:
    ```html
    <header class="sticky top-0 z-40 border-b border-[#e3e8ee] bg-white/80 backdrop-blur-xl">
    ```
    Change logo link text and desktop navigation wrapper to light style:
    ```html
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-sans font-normal text-[#0d253d] group">
                <span class="relative flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-full bg-[#533afd] text-xs font-semibold text-white shadow-level1 transition-transform duration-300 group-hover:scale-105">
                    <span>RT</span>
                </span>
                <span class="hidden xsm:block">
                    <span class="block leading-tight text-[#0d253d] group-hover:text-[#533afd] transition-colors text-sm font-semibold">Smart RT</span>
                    <span class="block text-[10px] font-normal text-[#64748d]">Dashboard Pengurus</span>
                </span>
            </a>
            <nav class="hidden max-w-4xl flex-wrap items-center gap-1 rounded-full bg-slate-100 p-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 sm:flex">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}" class="rounded-full px-3 py-1.5 transition-all duration-200 {{ $item['active'] ? 'bg-[#533afd] text-white shadow-level1' : 'hover:bg-slate-200 hover:text-[#0d253d]' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    ```
    And adjust the mobile bottom nav to use light background:
    ```html
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-[#e3e8ee] bg-white/95 px-4 py-3 shadow-level2 backdrop-blur-lg sm:hidden" aria-label="Navigasi utama">
        <div class="mx-auto flex max-w-md gap-1.5 overflow-x-auto text-[11px] font-semibold scrollbar-none">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" class="shrink-0 rounded-full px-3.5 py-2 text-center transition-all duration-200 {{ $item['active'] ? 'bg-[#533afd] text-white shadow-level1' : 'text-[#64748d] hover:bg-slate-100 hover:text-[#0d253d]' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
    ```

- [ ] **Step 2: Run test suite to verify no pages fail rendering**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 3: Commit**
    ```bash
    git add resources/views/components/layouts/app.blade.php
    git commit -m "style: redesign admin layout to Stripe light canvas theme"
    ```

---

### Task 2: Households, Residents & Inventory Dashboard Views Redesign

**Files:**
*   Modify: `resources/views/livewire/households/index.blade.php`
*   Modify: `resources/views/livewire/residents/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/inventory/index.blade.php`

- [ ] **Step 1: Re-design households index view to Light Canvas**
    Modify `resources/views/livewire/households/index.blade.php` to style header cards, create forms, and tables as white cards with hairline borders (`border-[#e3e8ee]`) and dark text (`text-[#0d253d]`). Form inputs should use `bg-white`, `border-[#a8c3de]`, `text-[#0d253d]`, and `focus:border-[#533afd]`.
    ```html
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
            <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Master data</p>
            <h1 class="mt-2 display-lg text-[#0d253d]">Data Rumah/KK</h1>
            <p class="mt-2 text-[#64748d]">Kelola rumah, kepala keluarga, status aktif, dan QR token per rumah.</p>
        </div>

        <!-- Form Section -->
        <section class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-[#0d253d]">{{ $editingId ? 'Perbarui Rumah/KK' : 'Tambah Rumah/KK Baru' }}</h2>
                <p class="mt-1 text-sm text-[#64748d]">Isi nomor rumah dan nama kepala keluarga untuk operasional RT.</p>
            </div>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-4 items-end">
                <div class="sm:col-span-1">
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nomor Rumah</label>
                    <input wire:model="house_number" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                    @error('house_number') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Alamat</label>
                    <input wire:model="address" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Kepala Keluarga</label>
                    <input wire:model="head_name" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                    @error('head_name') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <button class="rounded-full bg-[#533afd] px-5 py-3 font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition duration-150 text-xs">
                        {{ $editingId ? 'Perbarui' : 'Tambah' }}
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-4 py-3 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Batal</button>
                    @endif
                </div>
            </form>
        </section>

        <!-- Table Section -->
        <section class="rounded-lg bg-white border border-[#e3e8ee] overflow-hidden shadow-level1">
            <div class="border-b border-[#e3e8ee] px-5 py-4">
                <h2 class="text-base font-semibold text-[#0d253d]">Daftar Rumah/KK</h2>
                <p class="mt-1 text-xs text-[#64748d]">Status aktif menentukan rumah yang dipakai untuk operasional kas dan ronda.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                    <thead class="bg-[#f6f9fc] text-left text-[#64748d]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Nomor</th>
                            <th class="px-5 py-3 font-semibold">Kepala Keluarga</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e8ee] text-[#0d253d]">
                        @foreach ($this->households as $household)
                            <tr class="hover:bg-[#f6f9fc]/50 transition-colors">
                                <td class="px-5 py-3 font-semibold text-[#0d253d] tnum">{{ $household->house_number }}</td>
                                <td class="px-5 py-3 text-[#273951]">{{ $household->head_name }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                        {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-[#533afd]/10 border border-[#533afd]/30 px-3 py-1.5 text-xs font-semibold text-[#533afd] hover:bg-[#533afd]/20 transition duration-150">QR</a>
                                        <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                                        <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $household->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                            {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-[#e3e8ee] sm:hidden">
                @foreach ($this->households as $household)
                    <article class="p-5 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-[#0d253d] tnum">{{ $household->house_number }}</h3>
                                <p class="mt-1 text-sm text-[#64748d]">{{ $household->head_name }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-[#533afd]/10 border border-[#533afd]/30 px-3 py-1.5 text-xs font-semibold text-[#533afd] hover:bg-[#533afd]/20 transition duration-150">QR</a>
                            <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                            <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $household->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
    ```

- [ ] **Step 2: Re-design residents index view to Light Canvas**
    Modify `resources/views/livewire/residents/index.blade.php` to convert dark-blue panels into white panels with thin borders and gray input backgrounds.
    ```html
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
            <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Master data</p>
            <h1 class="mt-2 display-lg text-[#0d253d]">Data Warga</h1>
            <p class="mt-2 text-[#64748d]">Kelola warga aktif, nomor HP unik, rumah/KK, dan catatan ronda.</p>
        </div>

        <!-- Form Section -->
        <section class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1">
            <div class="mb-5">
                <h2 class="text-base font-semibold text-[#0d253d]">{{ $editingId ? 'Perbarui Warga' : 'Tambah Warga Baru' }}</h2>
                <p class="mt-1 text-sm text-[#64748d]">Pilih rumah/KK, isi nama, dan nomor HP aktif warga.</p>
            </div>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-5 items-end">
                <div>
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Rumah/KK</label>
                    <select wire:model="household_id" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                        <option value="">Pilih rumah</option>
                        @foreach ($this->households as $household)
                            <option value="{{ $household->id }}">{{ $household->house_number }} - {{ $household->head_name }}</option>
                        @endforeach
                    </select>
                    @error('household_id') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nama</label>
                    <input wire:model="name" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                    @error('name') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nomor HP</label>
                    <input wire:model="phone" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                    @error('phone') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Catatan Ronda</label>
                    <input wire:model="ronda_notes" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                </div>
                <div class="flex items-center gap-2">
                    <button class="rounded-full bg-[#533afd] px-5 py-3 font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition duration-150 text-xs">
                        {{ $editingId ? 'Perbarui' : 'Tambah' }}
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-4 py-3 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Batal</button>
                    @endif
                </div>
            </form>
        </section>

        <!-- Table Section -->
        <section class="rounded-lg bg-white border border-[#e3e8ee] overflow-hidden shadow-level1">
            <div class="border-b border-[#e3e8ee] px-5 py-4">
                <h2 class="text-base font-semibold text-[#0d253d]">Daftar Warga</h2>
                <p class="mt-1 text-xs text-[#64748d]">Nomor HP adalah identitas warga aktif untuk akses layanan.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                    <thead class="bg-[#f6f9fc] text-left text-[#64748d]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Nomor HP</th>
                            <th class="px-5 py-3 font-semibold">Rumah</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e8ee] text-[#0d253d]">
                        @foreach ($this->residents as $resident)
                            <tr class="hover:bg-[#f6f9fc]/50 transition-colors">
                                <td class="px-5 py-3 font-semibold text-[#0d253d]">{{ $resident->name }}</td>
                                <td class="px-5 py-3 text-[#273951] tnum">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</td>
                                <td class="px-5 py-3 text-[#273951] tnum">{{ $resident->household?->house_number }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                        {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                                        <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $resident->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                            {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-[#e3e8ee] sm:hidden">
                @foreach ($this->residents as $resident)
                    <article class="p-5 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-[#0d253d]">{{ $resident->name }}</h3>
                                <p class="mt-1 text-sm text-[#64748d]">No. <span class="tnum">{{ $resident->household?->house_number ?? '-' }}</span> · HP: <span class="tnum">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</span></p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        @if ($resident->ronda_notes)
                            <p class="mt-3 rounded-sm bg-slate-50 border border-[#e3e8ee] p-3 text-xs text-[#64748d]">{{ $resident->ronda_notes }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                            <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $resident->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
    ```

- [ ] **Step 3: Re-design inventory view to Light Canvas**
    Edit `resources/views/livewire/dashboard/inventory/index.blade.php`.
    Change its background grids and text colors to light white cards.
    ```html
    <!-- Header Section -->
    <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
        <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Inventaris RT</p>
        <h1 class="mt-2 display-lg text-[#0d253d]">Aset & Barang RT</h1>
    </div>
    ```
    Change create asset card and grid items:
    ```html
    <section class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1">
    ```

- [ ] **Step 4: Run test suite to verify no pages fail rendering**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 5: Commit**
    ```bash
    git add resources/views/livewire/households/index.blade.php resources/views/livewire/residents/index.blade.php resources/views/livewire/dashboard/inventory/index.blade.php
    git commit -m "style: redesign households, residents, and inventory views to light canvas theme"
    ```

---

### Task 3: Finance, Kas Summary & Review Fines Dashboard Views Redesign

**Files:**
*   Modify: `resources/views/livewire/dashboard/kas/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/kas/transactions.blade.php`
*   Modify: `resources/views/livewire/dashboard/denda/index.blade.php`

- [ ] **Step 1: Re-design kas index summary view**
    Modify `resources/views/livewire/dashboard/kas/index.blade.php` to use white cards, slate borders, and tabular figures for daily/weekly/monthly stats.
    ```html
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <h1 class="display-lg text-[#0d253d]">Rekap Kas</h1>
            <a href="{{ route('kas.transactions') }}" class="text-sm font-semibold text-[#533afd] hover:underline transition-colors">Daftar Transaksi</a>
        </div>

        <!-- Date Picker Card -->
        <div class="rounded-lg bg-white p-5 border border-[#e3e8ee] shadow-level1">
            <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Tanggal Acuan</label>
            <input wire:model.live="date" type="date" class="mt-2 rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base">
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 border border-[#e3e8ee] shadow-level1">
                <p class="text-xs font-semibold tracking-wider text-[#64748d] uppercase">Total Harian</p>
                <p class="mt-1 text-3xl font-light text-[#0d253d] tnum">{{ $this->rupiah($this->daily['total']) }}</p>
                <p class="mt-1.5 text-xs text-[#64748d]">Iuran <span class="tnum">{{ $this->rupiah($this->daily['iuran']) }}</span> &bull; Denda <span class="tnum">{{ $this->rupiah($this->daily['denda']) }}</span> &bull; Koreksi <span class="tnum">{{ $this->rupiah($this->daily['koreksi']) }}</span></p>
            </div>
            ...
        </div>
    ```

- [ ] **Step 2: Re-design kas transactions view**
    Modify `resources/views/livewire/dashboard/kas/transactions.blade.php` to convert the layout to the Light Canvas style:
    *   Set title and link styles.
    *   Set table container background to `bg-white border border-[#e3e8ee]` instead of white/10.
    *   Table header `bg-[#f6f9fc] text-[#64748d]`.
    *   Apply `tnum` class to dates, transaction IDs, and currency amounts.
    *   Pill buttons for cancel action and styling cancel input form modal.

- [ ] **Step 3: Re-design review denda index view**
    Modify `resources/views/livewire/dashboard/denda/index.blade.php` to style the candidate lists, table filters, actions, and modal overlay:
    *   Replace absolute dark backgrounds in dialog overlay: `bg-[#0d253d]/50 backdrop-blur-sm` instead of slate-950/60.
    *   Confirm modal wrapper: `bg-white border border-[#e3e8ee] shadow-level2 text-[#0d253d]`.
    *   Buttons: pill-shaped in Solid Indigo (`bg-[#533afd]`) or Outline Red (`border border-[#ea2261] text-[#ea2261]`).

- [ ] **Step 4: Run test suite to verify all tests pass**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 5: Commit**
    ```bash
    git add resources/views/livewire/dashboard/kas/index.blade.php resources/views/livewire/dashboard/kas/transactions.blade.php resources/views/livewire/dashboard/denda/index.blade.php
    git commit -m "style: redesign kas index, transactions, and denda review views to light canvas theme"
    ```

---

### Task 4: Admin Home, Announcements, Letters & Reports Dashboard Views Redesign

**Files:**
*   Modify: `resources/views/livewire/dashboard/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/announcements/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/letters/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/reports/index.blade.php`

- [ ] **Step 1: Redesign Admin Dashboard Home**
    Modify `resources/views/livewire/dashboard/index.blade.php`.
    Convert the dashboard index layout to light canvas:
    *   Header Welcome card styled in light cream (`bg-[#f5e9d4] border border-[#ebdcb9]`) or white.
    *   System Status card: white container with `bg-slate-50 border border-slate-200` details.
    *   Stats cards: white cards with level 1 shadows, numbers in large Inter 300 `tnum` and deep navy text.

- [ ] **Step 2: Redesign announcements view**
    Modify `resources/views/livewire/dashboard/announcements/index.blade.php`.
    *   Header title text color to `#0d253d`.
    *   Create draft form styled in white card (`bg-white border border-[#e3e8ee] shadow-level1`).
    *   Inputs: `bg-white border border-[#a8c3de] text-[#0d253d] focus:border-[#533afd]`.
    *   Announcements list cards: white panels with thin borders and Indigo primary/secondary action pills.

- [ ] **Step 3: Redesign letter requests view**
    Modify `resources/views/livewire/dashboard/letters/index.blade.php`.
    *   Convert container/card layout to light table view inside a white card panel.
    *   Style phone numbers and dates in the list with `tnum` class.
    *   Status workflow actions dropdown and pill badges.

- [ ] **Step 4: Redesign resident reports view**
    Modify `resources/views/livewire/dashboard/reports/index.blade.php`.
    *   Convert table/grid to Stripe light panel.
    *   Add `tnum` to phone numbers and dates.

- [ ] **Step 5: Run test suite to verify all tests pass**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 6: Commit**
    ```bash
    git add resources/views/livewire/dashboard/index.blade.php resources/views/livewire/dashboard/announcements/index.blade.php resources/views/livewire/dashboard/letters/index.blade.php resources/views/livewire/dashboard/reports/index.blade.php
    git commit -m "style: redesign admin home, announcements, letters, and reports views to light canvas theme"
    ```

---

### Task 5: Ronda Schedule & Voting Dashboard Views Redesign

**Files:**
*   Modify: `resources/views/livewire/dashboard/ronda/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/ronda/show.blade.php`
*   Modify: `resources/views/livewire/dashboard/votes/index.blade.php`
*   Modify: `resources/views/livewire/dashboard/votes/show.blade.php`

- [ ] **Step 1: Redesign ronda schedule index view**
    Modify `resources/views/livewire/dashboard/ronda/index.blade.php`.
    *   Update title and schedule calendar selectors.
    *   Style the dates table/cards as white cards with hairline borders.
    *   Add `tnum` to the dates, check-in ratios, and phone numbers.

- [ ] **Step 2: Redesign ronda schedule show (detail) view**
    Modify `resources/views/livewire/dashboard/ronda/show.blade.php`.
    *   Update layout of attendance statistics to white panels.
    *   Apply `tnum` to percentages, attendance counts, check-in timestamps.

- [ ] **Step 3: Redesign voting index view**
    Modify `resources/views/livewire/dashboard/votes/index.blade.php`.
    *   Create vote poll form card and active polls table.
    *   Render poll start/end dates with `tnum` class.

- [ ] **Step 4: Redesign voting show (detail) view**
    Modify `resources/views/livewire/dashboard/votes/show.blade.php`.
    *   Style voting results tally progress bars as clean gray paths filling with animated Indigo accents.
    *   Tally count numbers styled with `tnum`.

- [ ] **Step 5: Run test suite to verify all tests pass**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 6: Commit**
    ```bash
    git add resources/views/livewire/dashboard/ronda/index.blade.php resources/views/livewire/dashboard/ronda/show.blade.php resources/views/livewire/dashboard/votes/index.blade.php resources/views/livewire/dashboard/votes/show.blade.php
    git commit -m "style: redesign admin ronda and voting index/show views to light canvas theme"
    ```

---

### Task 6: Public Portal Cleanups & Polish

**Files:**
*   Modify: `resources/views/livewire/portal/ronda.blade.php`

- [ ] **Step 1: Apply tabular figures to numeric cells**
    Modify `resources/views/livewire/portal/ronda.blade.php` to add the `tnum` class to dates and attendance counts.
    *   On line 46: add class `tnum` to date text.
    *   On line 72: wrap count in `<span class="tnum">` tags.
    *   On line 85: add class `tnum` to mobile date card text.

- [ ] **Step 2: Run test suite to verify all tests pass**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 3: Commit**
    ```bash
    git add resources/views/livewire/portal/ronda.blade.php
    git commit -m "style: apply tabular figures (tnum) to dates and attendance counts in public ronda view"
    ```

---

### Task 7: Visual Verification & Final Checks

- [ ] **Step 1: Compile assets for production**
    Run: `ddev npm run build`
    Expected: Successful build output.

- [ ] **Step 2: Visual Inspection via Playwright**
    Run Playwright commands to visit `http://127.0.0.1:32772/dashboard`, check household pages, rekap pages, and take screenshots:
    *   Save screenshot of `/dashboard` as `dashboard_redesigned_light.png`
    *   Save screenshot of `/dashboard/kas` as `kas_redesigned_light.png`
    Copy these screenshots to the artifacts folder.

- [ ] **Step 3: Run final test suite check**
    Run: `ddev artisan test`
    Expected: All 170 tests PASS.

- [ ] **Step 4: Commit all final changes**
    ```bash
    git status
    ```
