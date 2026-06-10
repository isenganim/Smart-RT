<?php

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\LetterRequest;
use App\Services\ResidentLookup;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.public');
title('Surat Pengantar');
state(['phone' => '', 'type' => '', 'purpose' => '', 'done' => false, 'feedback' => null]);
rules(['phone' => ['required', 'string', 'max:30'], 'type' => ['required', 'in:domisili,usaha,tidak_mampu,pengantar_ktp,lainnya'], 'purpose' => ['required', 'string', 'min:5', 'max:2000']]);
$types = computed(fn () => LetterType::cases());

$submit = function (ResidentLookup $lookup) {
    $this->validate();
    $key = 'portal-letter:'.request()->getClientIp();
    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->done = false; $this->feedback = 'Terlalu banyak percobaan. Coba lagi nanti.'; return;
    }
    RateLimiter::hit($key, 60);
    $result = $lookup->resolve($this->phone);
    if (! $result->found()) {
        $this->done = false; $this->feedback = $result->message; return;
    }
    DB::transaction(function () use ($result) {
        $letter = LetterRequest::create(['phone' => $result->resident->phone, 'resident_id' => $result->resident->id, 'type' => $this->type, 'purpose' => $this->purpose, 'status' => LetterStatus::DIAJUKAN]);
        Audit::record(null, 'letter.submitted', 'letter_request', $letter->id, [
            'resident_id' => $result->resident->id,
            'type' => $this->type,
            'status' => LetterStatus::DIAJUKAN->value,
        ]);
    });

    $this->reset('phone', 'type', 'purpose'); $this->done = true; $this->feedback = null;
};
?>

<div class="space-y-5">
    <form wire:submit="submit" class="space-y-5 rounded-lg border border-[#e3e8ee] bg-white p-6 shadow-level1">
        <div>
            <h1 class="display-md text-[#0d253d]">Surat Pengantar</h1>
            <p class="mt-1 text-sm text-[#64748d]">Ajukan surat menggunakan nomor HP terdaftar.</p>
        </div>
        
        <x-portal.phone-field model="phone" />
        
        <div>
            <label class="text-sm font-semibold text-[#273951]">Jenis Surat</label>
            <select wire:model="type" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3 text-base text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300">
                <option value="">Pilih jenis</option>
                @foreach($this->types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('type')
                <p class="mt-1 text-sm text-[#ea2261] font-medium">{{ $message }}</p>
            @enderror
        </div>
        
        <div>
            <label class="text-sm font-semibold text-[#273951]">Keperluan</label>
            <textarea wire:model="purpose" rows="4" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3 text-base text-[#0d253d] placeholder-[#64748d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300" placeholder="Tuliskan keperluan pengajuan surat pengantar..."></textarea>
            @error('purpose')
                <p class="mt-1 text-sm text-[#ea2261] font-medium">{{ $message }}</p>
            @enderror
        </div>
        
        <button class="w-full rounded-full bg-[#533afd] py-3.5 font-sans font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition-all duration-150">
            Ajukan Surat
        </button>
    </form>
    
    @if ($done)
        <div class="rounded-lg border border-[#a7f3d0] bg-[#ecfdf5] p-5 text-center text-[#065f46] shadow-level1">
            <strong>Pengajuan terkirim</strong>
            <p class="mt-1 text-sm">Pengurus akan memproses pengajuan Anda.</p>
        </div>
    @elseif ($feedback)
        <div class="rounded-lg border border-[#fca5a5] bg-[#fef2f2] p-5 text-center text-[#991b1b] shadow-level1">
            {{ $feedback }}
        </div>
    @endif
</div>
