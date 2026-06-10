@props(['title' => 'Smart RT'])

@php
    $navItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Rumah/KK', 'route' => 'households.index', 'active' => request()->routeIs('households.*')],
        ['label' => 'Warga', 'route' => 'residents.index', 'active' => request()->routeIs('residents.*')],
        ['label' => 'Ronda', 'route' => 'ronda.index', 'active' => request()->routeIs('ronda.*')],
        ['label' => 'Sesi Scan', 'route' => 'scan-sessions.index', 'active' => request()->routeIs('scan-sessions.*')],
        ['label' => 'Denda', 'route' => 'denda.index', 'active' => request()->routeIs('denda.*')],
        ['label' => 'Kas', 'route' => 'kas.index', 'active' => request()->routeIs('kas.*')],
        ['label' => 'Pengumuman', 'route' => 'announcements.index', 'active' => request()->routeIs('announcements.*')],
        ['label' => 'Laporan', 'route' => 'reports.index', 'active' => request()->routeIs('reports.*')],
        ['label' => 'Surat', 'route' => 'letters.index', 'active' => request()->routeIs('letters.*')],
        ['label' => 'Voting', 'route' => 'votes.index', 'active' => request()->routeIs('votes.*')],
        ['label' => 'Inventaris', 'route' => 'inventory.index', 'active' => request()->routeIs('inventory.*')],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#533afd">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#f6f9fc] text-[#0d253d] antialiased font-sans selection:bg-[#533afd] selection:text-white">
    <div class="min-h-screen relative overflow-x-hidden pb-20 sm:pb-0">
        <!-- Ambient wash -->
        <div class="absolute inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 bg-[#533afd]/2"></div>
        </div>

        <header class="sticky top-0 z-40 border-b border-[#e3e8ee] bg-white/80 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-sans font-normal text-[#0d253d] group">
                        <span class="relative flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-full bg-[#533afd] text-xs font-semibold text-white shadow-level1 transition-transform duration-300 group-hover:scale-105">
                            <span>RT</span>
                        </span>
                        <span class="hidden xsm:block">
                            <span class="block leading-tight text-[#0d253d] group-hover:text-[#533afd] transition-colors text-sm font-semibold">Smart RT</span>
                            <span class="block text-[10px] font-normal text-[#64748d]">Dashboard Pengurus</span>
                        </span>
                    </a>
                    <nav class="hidden max-w-4xl flex-wrap items-center gap-1 rounded-full bg-slate-100 p-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 sm:flex">
                        @foreach ($navItems as $item)
                            <a href="{{ route($item['route']) }}" class="rounded-full px-3 py-1.5 transition-all duration-200 {{ $item['active'] ? 'bg-[#533afd] text-white shadow-level1' : 'hover:bg-slate-200 hover:text-[#0d253d]' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('portal.home') }}" class="text-xs font-medium text-[#64748d] hover:text-[#533afd] transition-colors" target="_blank" rel="noopener">Portal Warga &rarr;</a>
                    <livewire:auth.logout-button />
                </div>
            </div>
        </header>

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-[#e3e8ee] bg-white/95 px-4 py-3 shadow-level2 backdrop-blur-lg sm:hidden" aria-label="Navigasi utama">
            <div class="mx-auto flex max-w-md gap-1.5 overflow-x-auto text-[11px] font-semibold scrollbar-none">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}" class="shrink-0 rounded-full px-3.5 py-2 text-center transition-all duration-200 {{ $item['active'] ? 'bg-[#533afd] text-white shadow-level1' : 'text-[#64748d] hover:bg-slate-100 hover:text-[#0d253d]' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:py-10">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
