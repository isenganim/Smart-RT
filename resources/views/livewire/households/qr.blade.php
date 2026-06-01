<?php

use App\Models\Household;
use App\Support\QrCode;
use function Livewire\Volt\{state, computed, layout, mount, title};

state(['household' => null]);

layout('components.layouts.app');
title('QR Rumah');

mount(function (Household $household) {
    $this->household = $household;
});

$svg = computed(fn () => QrCode::svg($this->household->qr_token));

?>

<div class="mx-auto max-w-md space-y-4 text-center">
        <h1 class="text-xl font-semibold text-slate-900">QR Rumah {{ $household->house_number }}</h1>
        <p class="text-slate-600">{{ $household->head_name }}</p>
        <div class="inline-block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            {!! $this->svg !!}
        </div>
        <p class="text-xs text-slate-400">Token: {{ $household->qr_token }}</p>
        <div>
            <a href="{{ route('households.index') }}" class="text-emerald-700 hover:underline">Kembali</a>
        </div>
</div>
