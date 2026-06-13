<?php

use App\Models\RondaScanSession;
use App\Services\IuranScan;
use App\Services\PinGate;
use App\Services\ResidentLookup;
use App\Services\ScanOfficerGate;
use App\Support\Audit;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{layout, rules, state, title};

layout('components.layouts.public');
title('Scan Iuran');

state([
    'phone' => '',
    'pin' => '',
    'unlocked' => false,
    'sessionId' => null,
    'unlockError' => null,
    'token' => '',
    'lastResult' => null,
]);

rules([
    'phone' => ['required', 'string', 'max:30'],
    'pin' => ['required', 'string', 'max:6'],
]);

$unlock = function (PinGate $gate, ResidentLookup $lookup, ScanOfficerGate $officerGate) {
    $this->validate();

    $key = 'scan-unlock:'.request()->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->unlocked = false;
        $this->unlockError = 'Terlalu banyak percobaan. Coba lagi nanti.';

        return;
    }

    $lookupResult = $lookup->resolve($this->phone);
    if (! $lookupResult->found()) {
        RateLimiter::hit($key, 60);
        $this->unlocked = false;
        $this->unlockError = $lookupResult->message;

        return;
    }

    RateLimiter::hit($key, 60);

    $result = $gate->unlock($this->pin);

    if ($result->ok()) {
        $authorization = $officerGate->authorize($lookupResult->resident, $result->session);

        if (! $authorization->ok()) {
            $this->unlocked = false;
            $this->sessionId = null;
            $this->unlockError = $authorization->message;

            return;
        }

        $this->unlocked = true;
        $this->sessionId = $result->session->id;
        $this->unlockError = null;
        RateLimiter::clear($key);
    } else {
        $this->unlocked = false;
        $this->unlockError = $result->message;
    }
};

// Shared logic used by both manual scan() and camera-triggered scanDetectedToken().
$processScan = function (string $token, IuranScan $iuran, ResidentLookup $lookup, ScanOfficerGate $officerGate) {
    if (! $this->unlocked || ! $this->sessionId) {
        return;
    }

    $lookupResult = $lookup->resolve($this->phone);
    if (! $lookupResult->found()) {
        $this->unlocked = false;
        $this->sessionId = null;
        $this->unlockError = $lookupResult->message;

        return;
    }

    $session = RondaScanSession::find($this->sessionId);

    if (! $session || ! $session->isActive()) {
        $this->unlocked = false;
        $this->sessionId = null;
        $this->unlockError = 'PIN sudah kedaluwarsa.';

        return;
    }

    $authorization = $officerGate->authorize($lookupResult->resident, $session);

    if (! $authorization->ok()) {
        $this->unlocked = false;
        $this->sessionId = null;
        $this->unlockError = $authorization->message;

        return;
    }

    $result = $iuran->record($session, $token);

    if ($result->paid() && $result->transaction) {
        Audit::record(
            auth()->user(),
            'kas.iuran.scanned',
            'cash_transaction',
            $result->transaction->id,
            [
                'ronda_scan_session_id' => $session->id,
                'household_id' => $result->household?->id,
            ]
        );
    }

    $this->lastResult = [
        'paid' => $result->paid(),
        'status' => $result->status,
        'head' => $result->household?->head_name,
        'house' => $result->household?->house_number,
        'address' => $result->household?->address,
        'amount' => $result->transaction?->amount,
        'message' => $result->message,
    ];

    $this->reset('token');
};

// Manual form submission.
$scan = function (IuranScan $iuran, ResidentLookup $lookup, ScanOfficerGate $officerGate) {
    $this->processScan($this->token, $iuran, $lookup, $officerGate);
};

// Camera-detected token from the JS scanner.
$scanDetectedToken = function (string $token, IuranScan $iuran, ResidentLookup $lookup, ScanOfficerGate $officerGate) {
    $this->token = $token;
    $this->processScan($token, $iuran, $lookup, $officerGate);
};

?>

<div class="space-y-6">
    @unless ($unlocked)
        <div class="relative overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
            <h1 class="display-md text-[#0d253d]">Pindai Iuran Ronda</h1>
            <p class="mt-2 text-sm text-[#64748d] leading-relaxed">Masukkan PIN harian dari pengurus untuk membuka mode pindai.</p>

            <form wire:submit="unlock" class="mt-6 space-y-5">
                <x-portal.phone-field model="phone" label="Nomor HP Petugas" />
                <div>
                    <label class="block text-sm font-semibold text-[#273951]">PIN Harian</label>
                    <input wire:model="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3.5 text-center text-lg text-[#0d253d] tracking-widest focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base placeholder:text-[#64748d]">
                    @error('pin') <p class="mt-2 text-sm text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
                    Buka Mode Pindai
                </button>
            </form>

        </div>

        @if ($unlockError)
            <div class="rounded-lg bg-[#fef2f2] border border-[#fca5a5] p-6 text-center shadow-level1 animate-fade-in">
                <p class="text-sm font-medium text-[#991b1b] leading-relaxed">{{ $unlockError }}</p>
            </div>
        @endif
    @else
        <div class="relative overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
            <div class="flex items-center justify-between">
                <h1 class="display-md text-[#0d253d]">Pindai Iuran</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#ecfdf5] border border-[#a7f3d0] px-3 py-1 text-[11px] font-semibold text-[#065f46]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#059669] animate-pulse"></span>
                    PIN Aktif
                </span>
            </div>
            <p class="mt-2 text-sm text-[#64748d] leading-relaxed">Arahkan kamera ke QR rumah, atau masukkan kode QR secara manual.</p>

            {{-- Camera scanner panel --}}
            <div
                x-data
                x-on:iuran-qr-detected.window="$wire.scanDetectedToken($event.detail.token)"
                class="mt-6"
            >
                <div wire:ignore data-iuran-scanner class="space-y-3">
                    <div id="iuran-qr-reader" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-[#f6f9fc]"></div>

                    <div class="flex gap-2">
                        <button
                            id="iuran-start-camera"
                            type="button"
                            class="flex-1 rounded-full border border-[#533afd] bg-[#533afd]/10 px-4 py-2.5 text-sm font-semibold text-[#533afd] transition hover:bg-[#533afd]/20"
                        >
                            Mulai Kamera
                        </button>
                        <button
                            id="iuran-stop-camera"
                            type="button"
                            class="hidden flex-1 rounded-full border border-[#e3e8ee] bg-white px-4 py-2.5 text-sm font-semibold text-[#64748d] transition hover:bg-[#f6f9fc]"
                        >
                            Matikan Kamera
                        </button>
                    </div>

                    <p
                        id="iuran-scanner-status"
                        aria-live="polite"
                        class="text-center text-xs text-[#64748d]"
                    >
                        Kamera belum aktif.
                    </p>
                </div>
            </div>

            {{-- Manual token form --}}
            <form wire:submit="scan" class="mt-5 space-y-4 border-t border-[#e3e8ee] pt-5">
                <div>
                    <label for="iuran-token-input" class="block text-sm font-semibold text-[#273951]">Masukkan Kode QR Rumah Secara Manual</label>
                    <input
                        id="iuran-token-input"
                        wire:model="token"
                        type="text"
                        class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3.5 text-[#0d253d] placeholder-[#64748d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base"
                        placeholder="Hasil pindai akan muncul di sini"
                    >
                </div>
                <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
                    Terima Tunai Rp500
                </button>
            </form>
        </div>

        @if ($lastResult)
            @if ($lastResult['paid'])
                <div class="rounded-lg bg-[#ecfdf5] border border-[#a7f3d0] p-5 shadow-level1 relative overflow-hidden animate-fade-in flex flex-col gap-1 text-[#065f46]">
                    <p class="text-xs font-semibold tracking-wide uppercase text-[#059669]">Pembayaran Berhasil</p>
                    <p class="text-lg font-bold mt-1 tnum">Lunas Rp{{ number_format((int) $lastResult['amount'], 0, ',', '.') }}</p>
                    <p class="text-sm mt-1 font-medium">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                    @if ($lastResult['address']) <p class="text-xs opacity-80">{{ $lastResult['address'] }}</p> @endif
                </div>
            @elseif ($lastResult['status'] === 'already_paid')
                <div class="rounded-lg bg-[#fef3c7] border border-[#fde68a] p-5 shadow-level1 animate-fade-in flex flex-col gap-1 text-[#92400e]">
                    <p class="text-xs font-semibold tracking-wide uppercase text-[#b45309]">Pembayaran Sudah Tercatat</p>
                    <p class="text-sm font-medium leading-relaxed mt-1">{{ $lastResult['message'] }}</p>
                    <p class="text-xs mt-1">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                </div>
            @else
                <div class="rounded-lg bg-[#fef2f2] border border-[#fca5a5] p-6 text-center shadow-level1 animate-fade-in">
                    <p class="text-sm font-medium text-[#991b1b] leading-relaxed">{{ $lastResult['message'] }}</p>
                </div>
            @endif
        @endif
    @endunless

    <div class="text-center">
        <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748d] hover:text-[#533afd] transition-colors group">
            Kembali ke portal
        </a>
    </div>
</div>
