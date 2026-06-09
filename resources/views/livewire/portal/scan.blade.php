<?php

use App\Models\RondaScanSession;
use App\Services\IuranScan;
use App\Services\PinGate;
use App\Services\ResidentLookup;
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

$unlock = function (PinGate $gate, ResidentLookup $lookup) {
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
        $this->unlocked = true;
        $this->sessionId = $result->session->id;
        $this->unlockError = null;
        RateLimiter::clear($key);
    } else {
        $this->unlocked = false;
        $this->unlockError = $result->message;
    }
};

$scan = function (IuranScan $iuran, ResidentLookup $lookup) {
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

    $result = $iuran->record($session, $this->token);

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

?>

<div class="space-y-5">
    @unless ($unlocked)
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-semibold text-slate-900">Scan Iuran Ronda</h1>
            <p class="mt-1 text-sm text-slate-600">Masukkan PIN harian dari pengurus untuk membuka mode scan.</p>

            <form wire:submit="unlock" class="mt-4 space-y-4">
                <x-portal.phone-field model="phone" label="Nomor HP Petugas" />
                <div>
                    <label class="block text-sm font-medium text-slate-700">PIN Harian</label>
                    <input wire:model="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-center text-lg text-slate-900 tracking-widest caret-emerald-600 placeholder:text-slate-400">
                    @error('pin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Buka Mode Scan</button>
            </form>

            @if ($unlockError)
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">{{ $unlockError }}</p>
            @endif
        </div>
    @else
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-slate-900">Scan Iuran</h1>
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">PIN aktif</span>
            </div>
            <p class="mt-1 text-sm text-slate-600">Scan atau masukkan kode QR rumah untuk mencatat iuran Rp500.</p>

            <form wire:submit="scan" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kode QR Rumah</label>
                    <input wire:model="token" type="text" autofocus class="mt-1 w-full rounded-lg border-slate-300" placeholder="Hasil scan akan terisi di sini">
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Terima Cash Rp500</button>
            </form>
        </div>

        @if ($lastResult)
            @if ($lastResult['paid'])
                <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
                    <p class="text-lg font-semibold text-emerald-800">Lunas Rp{{ number_format((int) $lastResult['amount'], 0, ',', '.') }}</p>
                    <p class="text-sm text-emerald-700">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                    @if ($lastResult['address']) <p class="text-xs text-emerald-600">{{ $lastResult['address'] }}</p> @endif
                </div>
            @elseif ($lastResult['status'] === 'already_paid')
                <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
                    <p class="font-semibold text-amber-800">{{ $lastResult['message'] }}</p>
                    <p class="text-sm text-amber-700">{{ $lastResult['house'] }} - {{ $lastResult['head'] }}</p>
                </div>
            @else
                <div class="rounded-2xl bg-red-50 p-5 ring-1 ring-red-200">
                    <p class="text-sm text-red-700">{{ $lastResult['message'] }}</p>
                </div>
            @endif
        @endif
    @endunless

    <div class="text-center">
        <a href="{{ route('portal.home') }}" class="text-sm text-emerald-700 hover:underline">Kembali ke portal</a>
    </div>
</div>
