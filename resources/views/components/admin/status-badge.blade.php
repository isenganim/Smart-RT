@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        default => 'border-hairline bg-canvas-soft text-ink-mute',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-pill border px-2.5 py-1 text-xs font-medium', $classes]) }}>
    {{ $slot }}
</span>
