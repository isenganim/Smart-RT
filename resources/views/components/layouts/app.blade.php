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

    if (!function_exists('getMenuIcon')) {
        function getMenuIcon($route, $class = 'mr-2.5 h-5 w-5 shrink-0') {
            $svg = '';
            switch ($route) {
                case 'dashboard':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />';
                    break;
                case 'households.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M8.25 21v-8.25A2.25 2.25 0 0110.5 10.5h3a2.25 2.25 0 012.25 2.25V21" />';
                    break;
                case 'residents.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0110.089 21c-2.213 0-4.3-.645-6.07-1.757v-.11a4.125 4.125 0 017.533-2.492M9 10.5a3 3 0 11-6 0 3 3 0 016 0zm9-1.5a3 3 0 11-6 0 3 3 0 016 0z" />';
                    break;
                case 'ronda.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286zm0 0v18.5" />';
                    break;
                case 'scan-sessions.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.875zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.875zM13.5 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5z" />';
                    break;
                case 'denda.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />';
                    break;
                case 'kas.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5c.621 0 1.125.504 1.125 1.125v12.125c0 .621-.504 1.125-1.125 1.125H3.75A1.125 1.125 0 012.625 16.5V5.625C2.625 5.004 3.129 4.5 3.75 4.5zM18 10.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6 10.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />';
                    break;
                case 'announcements.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />';
                    break;
                case 'reports.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />';
                    break;
                case 'letters.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />';
                    break;
                case 'votes.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
                    break;
                case 'inventory.index':
                    $svg = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />';
                    break;
            }
            return $svg ? "<svg class=\"{$class}\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">{$svg}</svg>" : '';
        }
    }
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
                                    {!! getMenuIcon($item['route']) !!}
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

        <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-hairline bg-white/95 px-2 pt-3 shadow-level2 backdrop-blur lg:hidden" aria-label="Navigasi utama">
            @foreach ($groups as $items)
                @foreach ($items as $item)
                    @if (in_array($item['route'], $mobileRoutes, true))
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($item['active']) aria-current="page" @endif
                            class="flex flex-col items-center justify-center rounded-md py-1.5 px-1 text-center text-[10px] font-medium {{ $item['active'] ? 'text-primary' : 'text-ink-mute' }}"
                        >
                            {!! getMenuIcon($item['route'], 'h-5 w-5 mb-1 shrink-0') !!}
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
                class="flex flex-col items-center justify-center rounded-md py-1.5 px-1 text-center text-[10px] font-medium text-ink-mute"
            >
                <svg class="h-5 w-5 mb-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 5.25h16.5m-16.5-10.5h16.5" />
                </svg>
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
                                <div class="mt-2 grid grid-cols-2 gap-2.5">
                                    @foreach ($drawerItems as $item)
                                        <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center rounded-md border border-hairline px-3 text-sm font-medium text-ink-secondary">
                                            {!! getMenuIcon($item['route']) !!}
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
