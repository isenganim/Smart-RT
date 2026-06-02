@props(['model' => 'phone', 'label' => 'Nomor HP', 'id' => null])

@php
    $inputId = $id ?? str_replace(['.', '[', ']'], '-', $model);
@endphp

<div>
    <label for="{{ $inputId }}" class="block text-sm font-semibold text-slate-300">{{ $label }}</label>
    <div class="relative mt-2">
        <input
            id="{{ $inputId }}"
            wire:model="{{ $model }}"
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            placeholder="Contoh: 0812xxxxxxx atau +62812xxxx"
            class="w-full rounded-2xl border-white/10 bg-slate-950/60 px-4 py-3.5 text-white placeholder-slate-500 focus:border-emerald-500/50 focus:bg-slate-950 focus:ring-4 focus:ring-emerald-500/10 transition-all duration-300 text-base"
        >
    </div>
    @error($model) <p class="mt-2 text-sm text-red-400 font-medium">{{ $message }}</p> @enderror
</div>
