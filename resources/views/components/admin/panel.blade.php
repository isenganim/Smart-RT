@props(['padding' => true])

<section {{ $attributes->class([
    'rounded-lg border border-hairline bg-white shadow-level1',
    'p-5 sm:p-6' => $padding,
]) }}>{{ $slot }}</section>
