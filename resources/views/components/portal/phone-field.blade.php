@props(['model' => 'phone', 'label' => 'Nomor HP', 'id' => null])

@php
    $inputId = $id ?? str_replace(['.', '[', ']'], '-', $model);
@endphp

<div>
    <label for="{{ $inputId }}" class="block text-sm font-semibold text-[#273951]">{{ $label }}</label>
    <div class="relative mt-2">
        <input
            id="{{ $inputId }}"
            wire:model="{{ $model }}"
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            placeholder="Contoh: 0812xxxxxxx atau +62812xxxx"
            class="w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-3 text-[#0d253d] placeholder-[#64748d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base"
        >
    </div>
    @error($model) <p class="mt-2 text-sm text-[#ea2261] font-medium">{{ $message }}</p> @enderror
</div>
