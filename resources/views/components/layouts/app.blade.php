@props(['title' => 'Smart RT'])

@php
    $groups = [
        'Ringkasan' => [
            ['label' => 'Dashboard', 'mobile' => 'Beranda', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
        ],
        'Data Warga' => [
            ['label' => 'Rumah / KK', 'mobile' => 'Rumah', 'route' => 'households.index', 'active' => request()->routeIs('households.*')],
            ['label' => 'Warga', 'mobile' => 'Warga', 'route' => 'residents.index', 'active' => request()->routeIs('residents.*')],
        ],
        'Operasional' => [
            ['label' => 'Ronda', 'route' => 'ronda.index', 'active' => request()->routeIs('ronda.*')],
            ['label' => 'Sesi Scan', 'route' => 'scan-sessions.index', 'active' => request()->routeIs('scan-sessions.*')],
            ['label' => 'Denda', 'route' => 'denda.index', 'active' => request()->routeIs('denda.*')],
            ['label' => 'Kas', 'mobile' => 'Kas', 'route' => 'kas.index', 'active' => request()->routeIs('kas.*')],
        ],
        'Layanan' => [
            ['label' => 'Pengumuman', 'route' => 'announcements.index', 'active' => request()->routeIs('announcements.*')],
            ['label' => 'Laporan', 'route' => 'reports.index', 'active' => request()->routeIs('reports.*')],
            ['label' => 'Surat', 'route' => 'letters.index', 'active' => request()->routeIs('letters.*')],
            ['label' => 'Voting', 'route' => 'votes.index', 'active' => request()->routeIs('votes.*')],
            ['label' => 'Inventaris', 'route' => 'inventory.index', 'active' => request()->routeIs('inventory.*')],
        ],
    ];

    $mobileRoutes = ['dashboard', 'households.index', 'residents.index', 'kas.index'];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#533afd">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas-soft text-ink antialiased">
    <a href="#main-content" class="sr-only z-50 rounded-sm bg-primary px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <div x-data="{ moreOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="hidden border-r border-hairline bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col" aria-label="Navigasi dashboard">
            <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-3 border-b border-hairline px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">RT</span>
                <span>
                    <span class="block text-sm font-semibold text-ink">Smart RT</span>
                    <span class="block text-xs text-ink-mute">Dashboard Pengurus</span>
                </span>
            </a>

            <nav class="flex-1 overflow-y-auto px-3 py-5">
                @foreach ($groups as $group => $items)
                    <div class="mb-5">
                        <p class="px-3 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-mute">{{ $group }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($items as $item)
                                <a
                                    href="{{ route($item['route']) }}"
                                    @if ($item['active']) aria-current="page" @endif
                                    class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium transition {{ $item['active'] ? 'bg-primary text-white' : 'text-ink-secondary hover:bg-canvas-soft hover:text-ink' }}"
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-hairline p-4">
                <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-xs text-ink-mute">{{ auth()->user()->role->label() }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <x-admin.button variant="secondary" href="{{ route('portal.home') }}" target="_blank" rel="noopener">Portal</x-admin.button>
                    <livewire:auth.logout-button />
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-hairline bg-white/95 px-4 backdrop-blur lg:px-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 lg:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">RT</span>
                    <span class="text-sm font-semibold text-ink">Smart RT</span>
                </a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('portal.home') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-pill px-3 text-sm font-medium text-ink-mute hover:bg-canvas-soft hover:text-ink">
                        Portal Warga
                    </a>
                    <p class="hidden text-sm font-medium text-ink lg:block">{{ auth()->user()->name }}</p>
                </div>
            </header>

            <main id="main-content" class="mx-auto max-w-[90rem] px-4 py-6 pb-28 sm:px-6 lg:px-8 lg:py-8 lg:pb-8">
                {{ $slot }}
            </main>
        </div>

        <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-hairline bg-white/95 px-2 pt-2 shadow-level2 backdrop-blur lg:hidden" aria-label="Navigasi utama">
            @foreach ($groups as $items)
                @foreach ($items as $item)
                    @if (in_array($item['route'], $mobileRoutes, true))
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($item['active']) aria-current="page" @endif
                            class="flex min-h-11 items-center justify-center rounded-md px-1 text-center text-xs font-medium {{ $item['active'] ? 'text-primary' : 'text-ink-mute' }}"
                        >
                            {{ $item['mobile'] }}
                        </a>
                    @endif
                @endforeach
            @endforeach
            <button
                x-ref="moreTrigger"
                type="button"
                @click="moreOpen = true; $nextTick(() => $refs.moreClose.focus())"
                :aria-expanded="moreOpen"
                aria-controls="mobile-more-menu"
                class="min-h-11 rounded-md px-1 text-xs font-medium text-ink-mute"
            >
                Lainnya
            </button>
        </nav>

        <div
            x-cloak
            x-show="moreOpen"
            @keydown.escape.window="moreOpen = false; $nextTick(() => $refs.moreTrigger.focus())"
            class="fixed inset-0 z-50 lg:hidden"
        >
            <button
                type="button"
                @click="moreOpen = false; $nextTick(() => $refs.moreTrigger.focus())"
                class="absolute inset-0 bg-ink/40"
                aria-label="Tutup menu"
            ></button>
            <section
                x-ref="moreDialog"
                id="mobile-more-menu"
                role="dialog"
                aria-modal="true"
                aria-labelledby="mobile-more-title"
                @keydown.tab="
                    const focusable = [...$refs.moreDialog.querySelectorAll('a[href], button:not([disabled])')]
                        .filter((element) => element.offsetParent !== null);
                    if (focusable.length) {
                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];
                        if ($event.shiftKey && document.activeElement === first) {
                            $event.preventDefault();
                            last.focus();
                        } else if (!$event.shiftKey && document.activeElement === last) {
                            $event.preventDefault();
                            first.focus();
                        }
                    }
                "
                class="safe-bottom absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-xl bg-white p-5 shadow-level2"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 id="mobile-more-title" class="text-lg font-medium text-ink">Menu lainnya</h2>
                        <p class="text-sm text-ink-mute">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</p>
                    </div>
                    <button
                        x-ref="moreClose"
                        type="button"
                        @click="moreOpen = false; $nextTick(() => $refs.moreTrigger.focus())"
                        class="min-h-11 rounded-pill border border-hairline px-4 text-sm font-medium text-ink"
                    >
                        Tutup
                    </button>
                </div>
                <div class="mt-5 space-y-5">
                    @foreach ($groups as $group => $items)
                        @php($drawerItems = collect($items)->reject(fn ($item) => in_array($item['route'], $mobileRoutes, true)))
                        @if ($drawerItems->isNotEmpty())
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.1em] text-ink-mute">{{ $group }}</p>
                                <div class="mt-2 grid gap-2">
                                    @foreach ($drawerItems as $item)
                                        <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-md border border-hairline px-3 text-sm font-medium text-ink-secondary">
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6 flex gap-2 border-t border-hairline pt-4">
                    <x-admin.button variant="secondary" href="{{ route('portal.home') }}" target="_blank" rel="noopener">Portal Warga</x-admin.button>
                    <livewire:auth.logout-button />
                </div>
            </section>
        </div>
    </div>
    @livewireScripts
</body>
</html>
