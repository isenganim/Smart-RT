<?php

use App\Services\RondaCheckin;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules, layout, title};

state(['phone' => '', 'done' => false, 'feedback' => null]);

layout('components.layouts.public');
title('Check-in Ronda');

rules(['phone' => ['required', 'string', 'max:30']]);

$submit = function (RondaCheckin $checkin) {
    $this->validate();

    $key = 'portal-checkin:'.request()->ip();

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
        <div class="rounded-2xl bg-slate-900 border border-white/5 p-6 shadow-xl">
            <h1 class="text-xl font-bold text-white">Check-in Ronda</h1>
            <p class="mt-2 text-sm text-slate-400">
                Masukkan nomor HP terdaftar Anda untuk mencatat kehadiran ronda hari ini ({{ today()->translatedFormat('d M Y') }}).
            </p>

            <form wire:submit="submit" class="mt-6 space-y-5">
                <x-portal.phone-field model="phone" label="Nomor HP Petugas" />
                <button class="w-full rounded-2xl bg-emerald-500 py-3.5 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400 active:scale-[0.98] transition-all duration-150">
                    Check-in Hadir
                </button>
            </form>
        </div>

        @if ($done)
            <div class="rounded-2xl bg-emerald-950/40 border border-emerald-500/20 p-6 text-center shadow-lg relative overflow-hidden animate-fade-in">
                <div class="absolute -right-10 -top-10 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl"></div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 mb-3 ring-1 ring-emerald-500/20">✓</span>
                <p class="text-xs font-semibold text-emerald-400 tracking-wide uppercase">Check-in Berhasil</p>
                <p class="mt-2 text-sm text-white">Kehadiran ronda Anda telah berhasil dicatat. Terima kasih atas partisipasi Anda menjaga lingkungan!</p>
            </div>
        @elseif ($feedback)
            <div class="rounded-2xl bg-amber-950/40 border border-amber-500/20 p-6 text-center shadow-lg animate-fade-in">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-400 mb-3 ring-1 ring-amber-500/20">!</span>
                <p class="text-sm font-medium text-amber-300 leading-relaxed">{{ $feedback }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-2 items-center">
            <a href="{{ route('portal.ronda') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 hover:underline transition-colors">Lihat Jadwal Ronda</a>
            <a href="{{ route('portal.home') }}" class="text-sm font-semibold text-slate-400 hover:text-white hover:underline transition-colors mt-2">&larr; Kembali ke portal</a>
        </div>
</div>
