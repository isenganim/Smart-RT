@props(['title', 'description'])

<div {{ $attributes->class(['px-5 py-10 text-center']) }}>
    <h3 class="text-base font-medium text-ink">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-mute">{{ $description }}</p>
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
