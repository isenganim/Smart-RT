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
    <form wire:submit="submit" class="space-y-5 rounded-2xl border border-white/5 bg-slate-900 p-6 shadow-xl">
        <div><h1 class="text-xl font-bold text-white">Surat Pengantar</h1><p class="mt-1 text-sm text-slate-400">Ajukan surat menggunakan nomor HP terdaftar.</p></div>
        <x-portal.phone-field model="phone" />
        <div><label class="text-sm font-medium text-slate-300">Jenis Surat</label><select wire:model="type" class="mt-1 w-full rounded-xl border-white/10 bg-slate-950 text-white"><option value="">Pilih jenis</option>@foreach($this->types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('type')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-medium text-slate-300">Keperluan</label><textarea wire:model="purpose" rows="4" class="mt-1 w-full rounded-xl border-white/10 bg-slate-950 text-white"></textarea>@error('purpose')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror</div>
        <button class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950">Ajukan Surat</button>
    </form>
    @if ($done)<div class="rounded-2xl border border-emerald-500/20 bg-emerald-950/40 p-5 text-center text-emerald-300"><strong>Pengajuan terkirim</strong><p class="mt-1 text-sm">Pengurus akan memproses pengajuan Anda.</p></div>@elseif ($feedback)<div class="rounded-2xl border border-amber-500/20 bg-amber-950/40 p-5 text-center text-amber-300">{{ $feedback }}</div>@endif
</div>
