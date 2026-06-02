<?php

use App\Services\ResidentLookup;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{state, rules, layout, title};

state([
    'phone' => '',
    'verified' => false,
    'feedback' => null,
]);

layout('components.layouts.public');
title('Cek Nomor HP');

rules([
    'phone' => ['required', 'string', 'max:30'],
]);

$check = function (ResidentLookup $lookup) {
    $this->validate();

    $key = 'portal-verify:'.request()->getClientIp();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->verified = false;
        $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    $result = $lookup->resolve($this->phone);

    if ($result->found()) {
        $this->verified = true;
        $this->feedback = null;

        return;
    }

    // Only count failed lookups so legitimate residents are not rate limited.
    RateLimiter::hit($key, 60);
    $this->verified = false;
    $this->feedback = $result->message;
};

?>

<div class="space-y-6">
        <div class="rounded-2xl bg-slate-900 border border-white/5 p-6 shadow-xl">
            <h1 class="text-xl font-bold text-white">Cek Nomor HP</h1>
            <p class="mt-2 text-sm text-slate-400">
                Masukkan nomor HP Anda untuk memastikan data Anda sudah terdaftar dan aktif di sistem RT.
            </p>

            <form wire:submit="check" class="mt-6 space-y-5">
                <x-portal.phone-field model="phone" />
                <button class="w-full rounded-2xl bg-emerald-500 py-3.5 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400 active:scale-[0.98] transition-all duration-150">
                    Cek Sekarang
                </button>
            </form>
        </div>

        @if ($verified)
            <div class="rounded-2xl bg-emerald-950/40 border border-emerald-500/20 p-6 text-center shadow-lg relative overflow-hidden animate-fade-in">
                <div class="absolute -left-10 -bottom-10 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl"></div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 mb-3 ring-1 ring-emerald-500/20">✓</span>
                <p class="text-xs font-semibold text-emerald-400 tracking-wide uppercase">Nomor HP Terdaftar & Aktif</p>
                <p class="mt-2 text-sm font-medium text-emerald-100">Nomor ini sudah terdaftar di sistem RT.</p>
            </div>
        @elseif ($feedback)
            <div class="rounded-2xl bg-amber-950/40 border border-amber-500/20 p-6 text-center shadow-lg animate-fade-in">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-400 mb-3 ring-1 ring-amber-500/20">!</span>
                <p class="text-sm font-medium text-amber-300 leading-relaxed">{{ $feedback }}</p>
            </div>
        @endif

        <div class="text-center">
            <a href="{{ route('portal.home') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 hover:underline transition-colors">&larr; Kembali ke portal</a>
        </div>
</div>
