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

    return Carbon::canBeCreatedFromFormat($date, 'Y-m-d')
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
    <!-- Header Section -->
    <div class="flex items-center justify-between">
        <h1 class="display-lg text-white">Rekap Kas</h1>
        <a href="{{ route('kas.transactions') }}" class="text-sm font-semibold text-[#b9b9f9] hover:text-white transition-colors">Daftar Transaksi</a>
    </div>

    <!-- Date Picker Card -->
    <div class="rounded-lg bg-[#1c1e54]/40 p-5 border border-white/10 shadow-level1">
        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Tanggal Acuan</label>
        <input wire:model.live="date" type="date" class="mt-2 rounded-sm border border-white/10 bg-[#0d253d] px-4 py-2.5 text-white focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base">
    </div>

    <!-- Summary Cards -->
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-[#1c1e54]/40 p-5 border border-white/10 shadow-level1">
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Total Harian</p>
            <p class="mt-1 text-3xl font-light text-white tnum">{{ $this->rupiah($this->daily['total']) }}</p>
            <p class="mt-1.5 text-xs text-slate-300">Iuran <span class="tnum">{{ $this->rupiah($this->daily['iuran']) }}</span> · Denda <span class="tnum">{{ $this->rupiah($this->daily['denda']) }}</span> · Koreksi <span class="tnum">{{ $this->rupiah($this->daily['koreksi']) }}</span></p>
        </div>
        <div class="rounded-lg bg-[#1c1e54]/40 p-5 border border-white/10 shadow-level1">
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Total Mingguan</p>
            <p class="mt-1 text-3xl font-light text-white tnum">{{ $this->rupiah($this->weekly) }}</p>
        </div>
        <div class="rounded-lg bg-[#1c1e54]/40 p-5 border border-white/10 shadow-level1">
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Total Bulanan</p>
            <p class="mt-1 text-3xl font-light text-white tnum">{{ $this->rupiah($this->monthly) }}</p>
        </div>
    </div>

    <!-- Unpaid List -->
    <div class="rounded-lg bg-[#1c1e54]/40 p-6 border border-white/10 shadow-level1">
        <h2 class="text-base font-semibold text-white">Rumah Belum Bayar ({{ $this->ref()->format('d M Y') }})</h2>
        <ul class="mt-4 grid gap-2 sm:grid-cols-2">
            @forelse ($this->unpaid as $household)
                <li class="rounded-sm bg-[#0d253d] border border-white/5 px-3 py-2 text-sm text-slate-200 tnum">{{ $household->house_number }} - {{ $household->head_name }}</li>
            @empty
                <li class="text-sm text-slate-400 italic">Semua rumah aktif sudah bayar.</li>
            @endforelse
        </ul>
    </div>

    <!-- Missing Checkins List -->
    <div class="rounded-lg bg-[#1c1e54]/40 p-6 border border-white/10 shadow-level1">
        <h2 class="text-base font-semibold text-white">Warga Belum Check-in</h2>
        <ul class="mt-4 grid gap-2 sm:grid-cols-2">
            @forelse ($this->missingCheckins as $assignment)
                <li class="rounded-sm bg-[#0d253d] border border-white/5 px-3 py-2 text-sm text-slate-200 tnum">{{ $assignment->resident?->name }} - {{ $assignment->resident?->household?->house_number }}</li>
            @empty
                <li class="text-sm text-slate-400 italic">Tidak ada warga terjadwal yang belum check-in.</li>
            @endforelse
        </ul>
    </div>
</div>
