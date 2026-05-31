<?php

use App\Models\Household;
use App\Models\Resident;
use function Livewire\Volt\{computed};

$householdCount = computed(fn () => Household::query()->where('is_active', true)->count());
$residentCount = computed(fn () => Resident::query()->where('is_active', true)->count());

?>

<x-layouts.app title="Dashboard Pengurus">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard Pengurus</h1>
        <p class="text-slate-600">Selamat datang di Smart RT.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Rumah/KK Aktif</p>
                <p class="mt-1 text-3xl font-semibold text-emerald-700">{{ $this->householdCount }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">Warga Aktif</p>
                <p class="mt-1 text-3xl font-semibold text-emerald-700">{{ $this->residentCount }}</p>
            </div>
        </div>
    </div>
</x-layouts.app>
