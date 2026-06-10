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
    <div class="relative overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
        <h1 class="display-md text-[#0d253d]">Cek Nomor HP</h1>
        <p class="mt-2 text-sm text-[#64748d] leading-relaxed">
            Masukkan nomor HP Anda untuk memastikan data Anda sudah terdaftar dan aktif di sistem RT.
        </p>

        <form wire:submit="check" class="mt-6 space-y-5">
            <x-portal.phone-field model="phone" />
            <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
                Cek Sekarang
            </button>
        </form>
    </div>

    @if ($verified)
        <div class="rounded-lg bg-[#ecfdf5] border border-[#a7f3d0] p-6 text-center shadow-level1 relative overflow-hidden animate-fade-in">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#10b981]/15 text-[#059669] mb-3 ring-1 ring-[#10b981]/20">✓</span>
            <p class="text-xs font-semibold text-[#065f46] tracking-wide uppercase">Nomor HP Terdaftar & Aktif</p>
            <p class="mt-2 text-sm font-medium text-[#065f46]">Nomor ini sudah terdaftar di sistem RT.</p>
        </div>
    @elseif ($feedback)
        <div class="rounded-lg bg-[#fef2f2] border border-[#fca5a5] p-6 text-center shadow-level1 animate-fade-in">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#ef4444]/15 text-[#dc2626] mb-3 ring-1 ring-[#ef4444]/20">!</span>
            <p class="text-sm font-medium text-[#991b1b] leading-relaxed">{{ $feedback }}</p>
        </div>
    @endif

    <div class="text-center">
        <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748d] hover:text-[#533afd] transition-colors group">
            <span class="transition-transform duration-300 group-hover:-translate-x-1">&larr;</span>
            Kembali ke portal
        </a>
    </div>
</div>
