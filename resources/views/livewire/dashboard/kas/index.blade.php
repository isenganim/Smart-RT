<?php

use App\Services\KasReport;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app');
title('Rekap Kas');

state(['date' => null]);

mount(function () {
    $this->date = request()->query('date', today()->toDateString());
});

$report = fn () => app(KasReport::class);
$ref = function () {
    $date = (string) $this->date;

    return Carbon::hasFormat($date, 'Y-m-d')
        ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay()
        : today();
};

$daily = computed(fn () => $this->report()->daily($this->ref()));
$weekly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfWeek(), $this->ref()->copy()->endOfWeek()));
$monthly = computed(fn () => $this->report()->rangeTotal($this->ref()->copy()->startOfMonth(), $this->ref()->copy()->endOfMonth()));
$unpaid = computed(fn () => $this->report()->unpaidHouseholds($this->ref()));
$missingCheckins = computed(fn () => $this->report()->missingCheckins($this->ref()));

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-white sm:text-slate-900">Rekap Kas</h1>
        <a href="{{ route('kas.transactions') }}" class="text-sm text-emerald-300 hover:underline sm:text-emerald-700">Daftar Transaksi</a>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
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

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="font-medium text-slate-900">Warga Belum Check-in</h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            @forelse ($this->missingCheckins as $assignment)
                <li class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $assignment->resident?->name }} - {{ $assignment->resident?->household?->house_number }}</li>
            @empty
                <li class="text-sm text-slate-400">Tidak ada warga terjadwal yang belum check-in.</li>
            @endforelse
        </ul>
    </div>
</div>
