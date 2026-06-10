@props(['label', 'value', 'description' => null, 'href' => null])

@php($classes = 'block rounded-lg border border-hairline bg-white p-5 shadow-level1 transition hover:border-primary/30')

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        <p class="text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">{{ $label }}</p>
        <p class="tnum mt-3 text-3xl font-light tracking-tight text-ink">{{ $value }}</p>
        @if ($description)
            <p class="mt-2 text-xs leading-5 text-ink-mute">{{ $description }}</p>
        @endif
    </a>
@else
    <div {{ $attributes->class([$classes]) }}>
        <p class="text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">{{ $label }}</p>
        <p class="tnum mt-3 text-3xl font-light tracking-tight text-ink">{{ $value }}</p>
        @if ($description)
            <p class="mt-2 text-xs leading-5 text-ink-mute">{{ $description }}</p>
        @endif
    </div>
@endif
