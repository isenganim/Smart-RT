@props(['title' => 'Portal Warga'])

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
<body class="min-h-screen bg-slate-900 text-slate-100 antialiased font-sans">
    <div class="min-h-screen relative">
        <!-- Premium background decoration -->
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,0.15),_transparent_36rem),linear-gradient(180deg,_#020617_0%,_#0f172a_100%)]"></div>

        <div class="mx-auto flex min-h-screen max-w-xl flex-col shadow-2xl bg-slate-950/40 backdrop-blur-md border-x border-white/5">
            <header class="flex items-center justify-between px-6 py-5 border-b border-white/5 bg-slate-950/60 sticky top-0 backdrop-blur-md z-30">
                <a href="{{ route('portal.home') }}" class="flex items-center gap-2 font-semibold text-emerald-400">
                    <span class="grid h-8 w-8 place-items-center rounded-xl bg-emerald-400 text-xs font-bold text-slate-950 shadow-lg shadow-emerald-500/20">RT</span>
                    <span class="tracking-wide">Smart RT</span>
                </a>
                <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold text-emerald-400 ring-1 ring-emerald-500/20">Portal Warga</span>
            </header>

            <main class="flex-1 px-6 py-6 pb-20">
                {{ $slot }}
            </main>

            <footer class="px-6 py-6 text-center text-xs text-slate-500 border-t border-white/5 bg-slate-950/20">
                <p>Smart RT Warga Portal &bull; Hubungi pengurus RT jika data Anda belum terdaftar.</p>
            </footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>
