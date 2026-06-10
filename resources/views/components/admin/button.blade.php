@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-hairline bg-white text-ink hover:bg-canvas-soft',
        'ghost' => 'border border-transparent bg-transparent text-ink-mute hover:bg-canvas-soft hover:text-ink',
        'danger' => 'border border-ruby/30 bg-ruby/10 text-ruby hover:bg-ruby/15',
        default => 'border border-primary bg-primary text-white shadow-level1 hover:bg-primary-deep active:bg-primary-press',
    };

    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-pill px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</button>
@endif
