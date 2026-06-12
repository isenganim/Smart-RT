<?php

use App\Services\RondaCheckin;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules, layout, title};

state(['phone' => '', 'done' => false, 'feedback' => null]);

layout('components.layouts.public');
title('Absen Ronda');

rules(['phone' => ['required', 'string', 'max:30']]);

$submit = function (RondaCheckin $checkin) {
    $this->validate();

    $key = 'portal-checkin:'.request()->getClientIp();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->done = false;
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    $result = $checkin->checkIn($this->phone, today());

    if ($result->success()) {
        $this->done = true;
        $this->feedback = null;

        return;
    }

    // Only count failed check-ins so a successful resident is not rate limited.
    RateLimiter::hit($key, 60);
    $this->done = false;
    $this->feedback = $result->message;
};

?>

<div class="space-y-6">
    <div class="relative overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
        <h1 class="display-md text-[#0d253d]">Absen Ronda</h1>
        <p class="mt-2 text-sm text-[#64748d] leading-relaxed">
            Masukkan nomor HP terdaftar Anda untuk mencatat kehadiran ronda hari ini ({{ today()->translatedFormat('d M Y') }}).
        </p>

        <form wire:submit="submit" class="mt-6 space-y-5">
            <x-portal.phone-field model="phone" label="Nomor HP Petugas" />
            <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
                Catat Kehadiran
            </button>
        </form>
    </div>

    @if ($done)
        <div class="rounded-lg bg-[#ecfdf5] border border-[#a7f3d0] p-6 text-center shadow-level1 relative overflow-hidden animate-fade-in">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#10b981]/15 text-[#059669] mb-3 ring-1 ring-[#10b981]/20">✓</span>
            <p class="text-xs font-semibold text-[#065f46] tracking-wide uppercase">Kehadiran Tercatat</p>
            <p class="mt-2 text-sm text-[#065f46]">Kehadiran ronda Anda telah berhasil dicatat. Terima kasih atas partisipasi Anda menjaga lingkungan!</p>
        </div>
    @elseif ($feedback)
        <div class="rounded-lg bg-[#fef2f2] border border-[#fca5a5] p-6 text-center shadow-level1 animate-fade-in">
            <p class="text-sm font-medium text-[#991b1b] leading-relaxed">{{ $feedback }}</p>
        </div>
    @endif

    <div class="flex flex-col gap-3 items-center">
        <a href="{{ route('portal.ronda') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#533afd] hover:text-[#4434d4] transition-colors group">
            Lihat Jadwal Ronda
        </a>
        <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748d] hover:text-[#533afd] transition-colors group mt-1">
            Kembali ke portal
        </a>
    </div>
</div>
