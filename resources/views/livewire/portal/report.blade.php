<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Services\ResidentLookup;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{layout, rules, state, title};

layout('components.layouts.public');
title('Lapor Warga');
state(['phone' => '', 'category' => '', 'description' => '', 'done' => false, 'feedback' => null]);
rules(['phone' => ['required', 'string', 'max:30'], 'category' => ['required', 'in:Keamanan,Kebersihan,Fasilitas,Lainnya'], 'description' => ['required', 'string', 'min:5', 'max:2000']]);

$submit = function (ResidentLookup $lookup) {
    $this->validate();
    $key = 'portal-report:'.request()->getClientIp();

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

    DB::transaction(function () use ($result) {
        $report = Report::create(['phone' => $result->resident->phone, 'resident_id' => $result->resident->id, 'category' => $this->category, 'description' => $this->description, 'status' => ReportStatus::BARU]);
        Audit::record(null, 'report.submitted', 'report', $report->id, [
            'resident_id' => $result->resident->id,
            'category' => $this->category,
            'status' => ReportStatus::BARU->value,
        ]);
    });

    $this->reset('phone', 'category', 'description');
    $this->done = true;
    $this->feedback = null;
};
?>

<div class="space-y-5">
    <form wire:submit="submit" class="space-y-5 rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
        <div>
            <h1 class="display-md text-[#0d253d]">Lapor Warga</h1>
            <p class="mt-1 text-sm text-[#64748d]">Gunakan nomor HP warga aktif yang terdaftar.</p>
        </div>

        <x-portal.phone-field model="phone" />

        <div>
            <label class="text-sm font-semibold text-[#273951]">Kategori</label>
            <select wire:model="category" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3 text-base text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300">
                <option value="">Pilih kategori</option>
                <option>Keamanan</option>
                <option>Kebersihan</option>
                <option>Fasilitas</option>
                <option>Lainnya</option>
            </select>
            @error('category')
                <p class="mt-1 text-sm text-[#ea2261] font-medium">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-[#273951]">Deskripsi</label>
            <textarea wire:model="description" rows="4" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3 text-base text-[#0d253d] placeholder-[#64748d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300" placeholder="Jelaskan detail laporan Anda..."></textarea>
            @error('description')
                <p class="mt-1 text-sm text-[#ea2261] font-medium">{{ $message }}</p>
            @enderror
        </div>

        <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
            Kirim Laporan
        </button>
    </form>

    @if ($done)
        <div class="rounded-lg border border-[#a7f3d0] bg-[#ecfdf5] p-5 text-center text-[#065f46] shadow-level1">
            <strong>Laporan terkirim</strong>
            <p class="mt-1 text-sm">Pengurus akan menindaklanjuti laporan Anda.</p>
        </div>
    @elseif ($feedback)
        <div class="rounded-lg border border-[#fca5a5] bg-[#fef2f2] p-6 text-center shadow-level1 animate-fade-in">
            <p class="text-sm font-medium text-[#991b1b] leading-relaxed">{{ $feedback }}</p>
        </div>
    @endif
</div>
