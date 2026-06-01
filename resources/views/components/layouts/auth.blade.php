@props(['title' => 'Login'])

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
    <main class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.28),_transparent_30rem),linear-gradient(135deg,_#020617,_#0f172a_55%,_#064e3b)] px-4 py-8">
        <section class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white/95 shadow-2xl shadow-slate-950/30 ring-1 ring-white/30 backdrop-blur">
            <div class="bg-slate-950 px-8 py-8 text-center text-white">
                <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-emerald-400 text-lg font-bold text-slate-950 shadow-lg shadow-emerald-500/30">RT</div>
                <h1 class="text-3xl font-bold tracking-tight">Smart RT</h1>
                <p class="mt-2 text-sm text-slate-300">{{ $title }}</p>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Khusus pengurus RT</p>
            </div>
            <div class="p-8">
                {{ $slot }}
            </div>
        </section>
    </main>
    @livewireScripts
</body>
</html>
