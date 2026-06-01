<?php

use App\Models\Household;
use App\Models\Resident;
use function Livewire\Volt\{computed};

$householdCount = computed(fn () => Household::query()->where('is_active', true)->count());
$residentCount = computed(fn () => Resident::query()->where('is_active', true)->count());

?>

<x-layouts.app title="Dashboard Pengurus">
    <div class="space-y-6 sm:space-y-8">
        <div class="grid gap-4 sm:grid-cols-2 lg:hidden">
            <a href="{{ route('households.index') }}" class="rounded-[1.25rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200">
                <p class="text-sm font-semibold text-slate-500">Rumah/KK Aktif</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-slate-950">{{ $this->householdCount }}</p>
            </a>
            <a href="{{ route('residents.index') }}" class="rounded-[1.25rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200">
                <p class="text-sm font-semibold text-slate-500">Warga Aktif</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-slate-950">{{ $this->residentCount }}</p>
            </a>
        </div>

        <section class="overflow-hidden rounded-[1.5rem] bg-white shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 sm:rounded-[2rem]">
            <div class="grid gap-6 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900 p-6 text-white sm:gap-8 sm:p-10 lg:grid-cols-[1fr_20rem]">
                <div>
                    <p class="inline-flex rounded-full bg-emerald-400/10 px-3 py-1 text-sm font-semibold text-emerald-300 ring-1 ring-emerald-300/20">Sprint 1 siap digunakan</p>
                    <h1 class="mt-4 max-w-2xl text-3xl font-bold tracking-tight sm:mt-5 sm:text-5xl">Dashboard Pengurus RT yang rapi dan cepat.</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:mt-4 sm:text-base sm:leading-7">Pantau data rumah, warga aktif, dan fondasi audit Smart RT dari satu tempat.</p>
                    <div class="mt-5 grid gap-3 sm:mt-7 sm:flex sm:flex-wrap">
                        <a href="{{ route('households.index') }}" class="rounded-2xl bg-emerald-400 px-5 py-3 text-center text-sm font-bold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 hover:bg-emerald-300">Kelola Rumah</a>
                        <a href="{{ route('residents.index') }}" class="rounded-2xl bg-white/10 px-5 py-3 text-center text-sm font-bold text-white ring-1 ring-white/15 transition hover:bg-white/15">Kelola Warga</a>
                    </div>
                </div>
                <div class="rounded-[1.5rem] bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur">
                    <p class="text-sm font-medium text-slate-300">Status sistem</p>
                    <div class="mt-5 space-y-4">
                        <div class="flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3">
                            <span class="text-sm text-slate-300">Auth Pengurus</span>
                            <span class="text-sm font-bold text-emerald-300">Aktif</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3">
                            <span class="text-sm text-slate-300">Audit Log</span>
                            <span class="text-sm font-bold text-emerald-300">Aktif</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3">
                            <span class="text-sm text-slate-300">PWA</span>
                            <span class="text-sm font-bold text-emerald-300">Ready</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="group rounded-[1.5rem] bg-white p-6 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-900/10">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-500">Rumah/KK Aktif</p>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">⌂</span>
                </div>
                <p class="mt-5 text-5xl font-bold tracking-tight text-slate-950">{{ $this->householdCount }}</p>
                <p class="mt-2 text-sm text-slate-500">Rumah terdaftar dan aktif untuk operasional RT.</p>
            </div>
            <div class="group rounded-[1.5rem] bg-white p-6 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-900/10">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-500">Warga Aktif</p>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-sky-50 text-sky-700">👥</span>
                </div>
                <p class="mt-5 text-5xl font-bold tracking-tight text-slate-950">{{ $this->residentCount }}</p>
                <p class="mt-2 text-sm text-slate-500">Warga dengan nomor HP unik dan status aktif.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
