<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Services\ResidentLookup;
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

    Report::create(['phone' => $this->phone, 'resident_id' => $result->resident->id, 'category' => $this->category, 'description' => $this->description, 'status' => ReportStatus::BARU]);
    $this->reset('phone', 'category', 'description');
    $this->done = true;
    $this->feedback = null;
};
?>

<div class="space-y-5">
    <form wire:submit="submit" class="space-y-5 rounded-2xl border border-white/5 bg-slate-900 p-6 shadow-xl">
        <div><h1 class="text-xl font-bold text-white">Lapor Warga</h1><p class="mt-1 text-sm text-slate-400">Gunakan nomor HP warga aktif yang terdaftar.</p></div>
        <x-portal.phone-field model="phone" />
        <div><label class="text-sm font-medium text-slate-300">Kategori</label><select wire:model="category" class="mt-1 w-full rounded-xl border-white/10 bg-slate-950 text-white"><option value="">Pilih kategori</option><option>Keamanan</option><option>Kebersihan</option><option>Fasilitas</option><option>Lainnya</option></select>@error('category')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror</div>
        <div><label class="text-sm font-medium text-slate-300">Deskripsi</label><textarea wire:model="description" rows="4" class="mt-1 w-full rounded-xl border-white/10 bg-slate-950 text-white"></textarea>@error('description')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror</div>
        <button class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950">Kirim Laporan</button>
    </form>
    @if ($done)<div class="rounded-2xl border border-emerald-500/20 bg-emerald-950/40 p-5 text-center text-emerald-300"><strong>Laporan terkirim</strong><p class="mt-1 text-sm">Pengurus akan menindaklanjuti laporan Anda.</p></div>@elseif ($feedback)<div class="rounded-2xl border border-amber-500/20 bg-amber-950/40 p-5 text-center text-amber-300">{{ $feedback }}</div>@endif
</div>
