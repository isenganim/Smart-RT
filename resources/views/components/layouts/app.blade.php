@props(['title' => 'Smart RT'])

@php
    $navItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Rumah/KK', 'route' => 'households.index', 'active' => request()->routeIs('households.*')],
        ['label' => 'Warga', 'route' => 'residents.index', 'active' => request()->routeIs('residents.*')],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title }} - Smart RT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.22),_transparent_32rem),linear-gradient(180deg,_#020617_0%,_#0f172a_18rem,_#f8fafc_18rem)] pb-20 sm:pb-0">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/85 backdrop-blur-xl">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-white">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-emerald-400 text-sm font-bold text-slate-950 shadow-lg shadow-emerald-500/20">RT</span>
                        <span>
                            <span class="block leading-tight">Smart RT</span>
                            <span class="block text-xs font-normal text-slate-400">Dashboard Pengurus</span>
                        </span>
                    </a>
                    <nav class="hidden items-center gap-2 rounded-full bg-white/5 p-1 text-sm font-medium text-slate-300 ring-1 ring-white/10 sm:flex">
                        @foreach ($navItems as $item)
                            <a href="{{ route($item['route']) }}" class="rounded-full px-4 py-2 transition {{ $item['active'] ? 'bg-white text-slate-950 shadow-sm' : 'hover:bg-white/10 hover:text-white' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
                <livewire:auth.logout-button />
            </div>
        </header>

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-3 py-2 shadow-2xl shadow-slate-950/15 backdrop-blur sm:hidden" aria-label="Navigasi utama">
            <div class="mx-auto grid max-w-md grid-cols-3 gap-2 text-xs font-semibold">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}" class="rounded-2xl px-3 py-2.5 text-center transition {{ $item['active'] ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-6 sm:py-10">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
